<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\StoreOrderServicePackageRequest;
use App\Http\Requests\Api\V1\Orders\UpdateOrderServicePackageRequest;
use App\Http\Resources\Api\V1\Orders\OrderServicePackageResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\OrderServicePackage;
use App\Modules\Packages\Models\Package;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Colis pris en charge par un service de commande.
 *
 * C'est la relation `OrderServicePackage` du diagramme : un même colis peut
 * être servi par plusieurs prestations — chargé ici, livré là — et chaque
 * liaison porte sa propre quantité et ses consignes de manutention.
 *
 * Elle n'était jusqu'ici créée qu'à la création complète d'une commande. Aucune
 * route ne permettait de l'ajouter ni de la retirer ensuite, alors que le
 * modèle la déclare modifiable.
 */
class OrderServicePackageController extends Controller
{
    use ResolvesOrderScope;

    /** @var array<string, string> colonne => champ d'API */
    private const array MAPPING = [
        'quantity' => 'quantity',
        'handling_instructions' => 'handlingInstructions',
        'status' => 'status',
    ];

    /**
     * Lister les colis pris en charge par un service.
     *
     * Permission requise : `order_services.view`.
     */
    public function index(Order $order, OrderService $orderService): JsonResponse
    {
        $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('manageServices', [$order, 'view']);

        $links = $orderService->servicePackages()->with('package')->get();

        return ApiResponse::ok(OrderServicePackageResource::collection($links));
    }

    /**
     * Rattacher un colis à un service.
     *
     * Permission requise : `order_services.update`. Le colis doit appartenir à
     * la même commande, et un colis ne se rattache qu'une fois au même service :
     * deux liaisons rendraient la quantité prise en charge indéterminée.
     */
    public function store(StoreOrderServicePackageRequest $request, Order $order, OrderService $orderService): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('manageServices', [$order, 'update']);
        $this->assertOrderIsEditable($order);

        $data = $request->validated();
        $package = $this->findPackage($order, $data['packageId']);
        $this->assertNotAlreadyLinked($orderService, $package);

        $link = $orderService->servicePackages()->create(array_merge(
            InputMapper::map($data, self::MAPPING),
            ['package_id' => $package->id, 'status' => $data['status'] ?? 'pending'],
        ));

        $this->audit($request, $organizationId, 'created', $link, null, $link->toArray());

        return ApiResponse::created(new OrderServicePackageResource($link->load('package')));
    }

    /**
     * Modifier une prise en charge.
     *
     * Permission requise : `order_services.update`.
     */
    public function update(UpdateOrderServicePackageRequest $request, Order $order, OrderService $orderService, OrderServicePackage $servicePackage): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('manageServices', [$order, 'update']);
        $this->assertOrderIsEditable($order);
        $this->assertBelongsToService($orderService, $servicePackage);

        $old = $servicePackage->toArray();
        $servicePackage->update(InputMapper::map($request->validated(), self::MAPPING));
        $this->audit($request, $organizationId, 'updated', $servicePackage, $old, $servicePackage->fresh()?->toArray());

        return ApiResponse::ok(new OrderServicePackageResource($servicePackage->fresh()?->load('package')));
    }

    /**
     * Retirer un colis d'un service.
     *
     * Permission requise : `order_services.update`. Le colis lui-même n'est pas
     * supprimé : seule la prise en charge par cette prestation disparaît.
     *
     * @response 204
     */
    public function destroy(Request $request, Order $order, OrderService $orderService, OrderServicePackage $servicePackage): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('manageServices', [$order, 'update']);
        $this->assertOrderIsEditable($order);
        $this->assertBelongsToService($orderService, $servicePackage);

        $this->audit($request, $organizationId, 'deleted', $servicePackage, $servicePackage->toArray(), null);
        $servicePackage->delete();

        return ApiResponse::noContent();
    }

    private function findPackage(Order $order, string $packageId): Package
    {
        $package = $order->packages()->find($packageId);

        abort_if($package === null, 404, 'Colis introuvable pour cette commande.');

        return $package;
    }

    private function assertNotAlreadyLinked(OrderService $orderService, Package $package): void
    {
        $exists = $orderService->servicePackages()->where('package_id', $package->id)->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'packageId' => ['Ce colis est déjà pris en charge par ce service.'],
            ]);
        }
    }

    private function assertBelongsToService(OrderService $orderService, OrderServicePackage $link): void
    {
        abort_unless(
            $link->order_service_id === $orderService->id,
            404,
            'Prise en charge introuvable pour ce service.',
        );
    }
}
