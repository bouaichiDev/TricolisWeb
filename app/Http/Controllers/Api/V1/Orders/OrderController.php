<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\DuplicateOrderRequest;
use App\Http\Requests\Api\V1\Orders\ListOrderRequest;
use App\Http\Requests\Api\V1\Orders\StoreOrderRequest;
use App\Http\Requests\Api\V1\Orders\UpdateOrderRequest;
use App\Http\Requests\Api\V1\Orders\UpdateOrderStatusRequest;
use App\Http\Resources\Api\V1\Orders\OrderDetailResource;
use App\Http\Resources\Api\V1\Orders\OrderListResource;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Actions\CreateFullOrder;
use App\Modules\Orders\Actions\DuplicateOrder;
use App\Modules\Orders\DTOs\CreateOrderData;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Queries\OrderListQuery;
use App\Modules\Orders\Services\OrderScopeGuard;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Commandes de transport.
 */
class OrderController extends Controller
{
    use ResolvesOrderScope;

    /** @var array<string, string> */
    private const array MAPPING = [
        'external_reference' => 'externalReference', 'customer_reference' => 'customerReference',
        'order_type' => 'orderType', 'group_code' => 'groupCode', 'order_date' => 'orderDate',
        'currency_code' => 'currencyCode', 'internal_remark' => 'internalRemark', 'worker_remark' => 'workerRemark',
    ];

    /**
     * Lister les commandes de l'organisation active.
     *
     * Permission requise : `orders.view`. Recherche sur le numéro et les
     * références ; filtres `customerId`, `agencyId`, `depotId`, `status`,
     * `source`, `orderType`, `requestedDate`, `city` (ville d'un service),
     * `fromCatalog`, `createdFrom`, `createdTo`.
     */
    public function index(ListOrderRequest $request, OrderListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Order::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated($paginator->through(fn (Order $order) => new OrderListResource($order)));
    }

    /**
     * Créer une commande complète.
     *
     * Permission requise : `orders.create`. Lignes, colis, affectations
     * colis-lignes, services, contacts et colis servis sont créés dans une seule
     * transaction : si une sous-opération échoue, rien ne subsiste. Les erreurs
     * portent le chemin exact du champ fautif, par exemple
     * `services.0.addressId`. Le numéro est attribué par la séquence.
     */
    public function store(StoreOrderRequest $request, CreateFullOrder $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Order::class, $organizationId]);

        $order = $action->execute(
            CreateOrderData::fromArray($request->validated()),
            $organizationId,
            $request->user(),
            $request,
        );

        return ApiResponse::created(new OrderDetailResource($order->load(['lines', 'packages', 'orderServices'])));
    }

    /**
     * Consulter une commande.
     *
     * Permission requise : `orders.view`. Le détail inclut client, agence,
     * dépôt, lignes, colis et services.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->guardOrder($order);
        $this->authorize('view', $order);

        return ApiResponse::ok(new OrderDetailResource(
            $order->load(['customer', 'agency', 'depot', 'lines', 'packages', 'orderServices.service', 'orderServices.contacts']),
        ));
    }

    /**
     * Modifier l'en-tête d'une commande.
     *
     * Permission requise : `orders.update`. Au-delà du statut `CONFIRMED`, le
     * contenu est figé et la requête est refusée en 409.
     */
    public function update(UpdateOrderRequest $request, Order $order, OrderScopeGuard $guard): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('update', $order);
        $this->assertOrderIsEditable($order);

        $data = $request->validated();
        $old = $order->toArray();

        $attributes = InputMapper::map($data, self::MAPPING);

        if (array_key_exists('depotId', $data)) {
            $attributes['depot_id'] = $data['depotId'] === null ? null : $guard->depot($data['depotId'], $organizationId)->id;
        }

        $attributes['updated_by'] = $request->user()->id;
        $order->update($attributes);
        $this->audit($request, $organizationId, 'updated', $order, $old, $order->fresh()->toArray());

        return ApiResponse::ok(new OrderDetailResource($order->fresh()));
    }

    /**
     * Changer le statut d'une commande.
     *
     * Permission requise : `orders.change_status`. La transition doit être
     * autorisée par le workflow ; un motif est obligatoire pour annuler. Les
     * statuts de planification et de facturation ne sont pas assignables ici.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, ChangeOrderStatus $action): JsonResponse
    {
        $this->guardOrder($order);
        $this->authorize('changeStatus', $order);

        $data = $request->validated();
        $target = OrderStatus::from($data['status']);

        if ($target === OrderStatus::CANCELLED) {
            $this->authorize('cancel', $order);
        }

        $action->execute($order, $target, $request->user(), $data['reasonCode'] ?? null, $data['reasonText'] ?? null, $request);

        return ApiResponse::ok(new OrderDetailResource($order->fresh()));
    }

    /**
     * Dupliquer une commande.
     *
     * Permission requise : `orders.duplicate`. La copie repart en brouillon avec
     * un nouveau numéro. Numéro d'origine, historique, audit, planification,
     * facturation et données d'exécution ne sont jamais copiés ; les documents
     * ne le sont que sur demande explicite.
     */
    public function duplicate(DuplicateOrderRequest $request, Order $order, DuplicateOrder $action): JsonResponse
    {
        $this->guardOrder($order);
        $this->authorize('duplicate', $order);

        $copy = $action->execute($order, $request->options(), $request->user(), $request);

        return ApiResponse::created(new OrderDetailResource($copy->load(['lines', 'packages', 'orderServices'])));
    }

    /**
     * Supprimer une commande.
     *
     * Permission requise : `orders.delete`. Seule une commande encore modifiable
     * peut être supprimée ; au-delà, l'annulation est la bonne opération.
     *
     * @response 204
     */
    public function destroy(Request $request, Order $order): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('delete', $order);

        if (! $order->allowsContentChanges()) {
            return ApiResponse::error('Une commande engagée ne se supprime pas : annulez-la.', 409);
        }

        $this->audit($request, $organizationId, 'deleted', $order, $order->toArray(), null);
        $order->delete();

        return ApiResponse::noContent();
    }
}
