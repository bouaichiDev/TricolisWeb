<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\StoreOrderServiceRequest;
use App\Http\Requests\Api\V1\Orders\UpdateOrderServiceRequest;
use App\Http\Requests\Api\V1\Orders\UpdateOrderServiceStatusRequest;
use App\Http\Resources\Api\V1\Orders\OrderServiceResource;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Services\OrderScopeGuard;
use App\Modules\Orders\Services\OrderServiceUniqueness;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Services d'une commande — l'unité de planification du modèle.
 *
 * Chaque service porte son adresse, son créneau, ses contacts et ses colis.
 * La facturation future s'appuiera dessus, mais aucun prix n'est calculé ici :
 * les montants sont enregistrés tels que fournis.
 */
class OrderServiceController extends Controller
{
    use ResolvesOrderScope;

    /** @var array<string, string> */
    private const array MAPPING = [
        'service_number' => 'serviceNumber', 'sequence' => 'sequence',
        'requested_date' => 'requestedDate', 'requested_from' => 'requestedFrom', 'requested_to' => 'requestedTo',
        'quantity' => 'quantity', 'unit' => 'unit',
        'required_time_minutes' => 'requiredTimeMinutes', 'remaining_time_minutes' => 'remainingTimeMinutes',
        'weight' => 'weight', 'volume' => 'volume', 'package_count' => 'packageCount',
        'customer_unit_price' => 'customerUnitPrice', 'customer_total_price' => 'customerTotalPrice',
        'provider_unit_cost' => 'providerUnitCost', 'provider_total_cost' => 'providerTotalCost',
        'instructions' => 'instructions', 'status' => 'status',
    ];

    public function __construct(
        private readonly OrderScopeGuard $scope,
        private readonly OrderServiceUniqueness $uniqueness,
    ) {}

    /**
     * Lister les services d'une commande.
     *
     * Permission requise : `order_services.view`. Filtre `status`, tri par
     * séquence par défaut.
     */
    public function index(ListRequest $request, Order $order): JsonResponse
    {
        $this->guardOrder($order);
        $this->authorize('manageServices', [$order, 'view']);

        $query = $order->orderServices()->with(['service', 'address']);

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $paginator = $query->orderBy($request->getSort('sequence', ['sequence', 'requested_date', 'created_at']), $request->getDirection())
            ->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (OrderService $service) => new OrderServiceResource($service)));
    }

    /**
     * Ajouter un service à une commande.
     *
     * Permission requise : `order_services.create`. Le service et l'adresse
     * doivent appartenir à l'organisation active. Le numéro et la séquence sont
     * uniques dans la commande.
     */
    public function store(StoreOrderServiceRequest $request, Order $order): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('manageServices', [$order, 'create']);
        $this->assertOrderIsEditable($order);

        $data = $request->validated();
        $this->uniqueness->assert($order, $data['serviceNumber'], $data['sequence']);

        $attributes = InputMapper::map($data, self::MAPPING);
        $attributes['service_id'] = $this->scope->service($data['serviceId'], $organizationId)->id;
        $attributes['address_id'] = $this->scope->address($data['addressId'], $organizationId)->id;
        $attributes['remaining_time_minutes'] ??= $data['requiredTimeMinutes'];
        $attributes['status'] ??= OrderServiceStatus::DRAFT->value;

        $service = $order->orderServices()->create($attributes);
        $this->audit($request, $organizationId, 'created', $service, null, $service->toArray());

        return ApiResponse::created(new OrderServiceResource($service));
    }

    /**
     * Consulter un service de commande.
     *
     * Permission requise : `order_services.view`.
     */
    public function show(Request $request, Order $order, OrderService $orderService): JsonResponse
    {
        $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('manageServices', [$order, 'view']);

        return ApiResponse::ok(new OrderServiceResource($orderService->load(['service', 'address', 'contacts', 'servicePackages'])));
    }

    /**
     * Modifier un service de commande.
     *
     * Permission requise : `order_services.update`. Le statut ne se change pas
     * ici : il relève de l'endpoint dédié.
     */
    public function update(UpdateOrderServiceRequest $request, Order $order, OrderService $orderService): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('manageServices', [$order, 'update']);
        $this->assertOrderIsEditable($order);

        $data = $request->validated();
        $old = $orderService->toArray();

        $this->uniqueness->assert(
            $order,
            $data['serviceNumber'] ?? null,
            $data['sequence'] ?? null,
            $orderService->id,
        );

        $attributes = InputMapper::map($data, self::MAPPING);

        if (isset($data['serviceId'])) {
            $attributes['service_id'] = $this->scope->service($data['serviceId'], $organizationId)->id;
        }

        if (isset($data['addressId'])) {
            $attributes['address_id'] = $this->scope->address($data['addressId'], $organizationId)->id;
        }

        $orderService->update($attributes);
        $this->audit($request, $organizationId, 'updated', $orderService, $old, $orderService->fresh()->toArray());

        return ApiResponse::ok(new OrderServiceResource($orderService->fresh()));
    }

    /**
     * Changer le statut d'un service.
     *
     * Permission requise : `order_services.change_status`. Le changement est
     * audité avec son ancienne et sa nouvelle valeur.
     */
    public function updateStatus(UpdateOrderServiceStatusRequest $request, Order $order, OrderService $orderService): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('manageServices', [$order, 'change_status']);

        $old = ['status' => $orderService->status?->value];
        $orderService->update(['status' => $request->validated('status')]);
        $this->audit($request, $organizationId, 'status_changed', $orderService, $old, ['status' => $orderService->status?->value]);

        return ApiResponse::ok(new OrderServiceResource($orderService->fresh()));
    }

    /**
     * Retirer un service d'une commande.
     *
     * Permission requise : `order_services.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Order $order, OrderService $orderService): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('manageServices', [$order, 'delete']);
        $this->assertOrderIsEditable($order);

        $this->audit($request, $organizationId, 'deleted', $orderService, $orderService->toArray(), null);
        $orderService->delete();

        return ApiResponse::noContent();
    }
}
