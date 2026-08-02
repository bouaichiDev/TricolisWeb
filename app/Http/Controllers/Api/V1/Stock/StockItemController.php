<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Stock\ListStockItemRequest;
use App\Http\Requests\Api\V1\Stock\StoreStockItemRequest;
use App\Http\Requests\Api\V1\Stock\UpdateStockItemRequest;
use App\Http\Resources\Api\V1\Stock\StockItemDetailResource;
use App\Http\Resources\Api\V1\Stock\StockItemListResource;
use App\Modules\Customers\Models\Customer;
use App\Modules\Stock\Actions\CreateStockItemAction;
use App\Modules\Stock\Actions\DeleteStockItemAction;
use App\Modules\Stock\Actions\UpdateStockItemAction;
use App\Modules\Stock\DTOs\CreateStockItemData;
use App\Modules\Stock\DTOs\UpdateStockItemData;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Queries\StockItemListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Articles de stock des clients.
 */
class StockItemController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les articles de stock.
     *
     * Permission requise : `stock_items.view`. Le périmètre passe par le
     * client, l'article ne portant pas d'organisation.
     */
    public function index(ListStockItemRequest $request, StockItemListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [StockItem::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId));
    }

    /**
     * Créer un article de stock.
     *
     * Permission requise : `stock_items.create`. Ni quantité ni emplacement :
     * le stock réel vit dans les soldes.
     */
    public function store(StoreStockItemRequest $request, CreateStockItemAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [StockItem::class, $organizationId]);

        $item = $action->execute(
            CreateStockItemData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new StockItemDetailResource($item));
    }

    /**
     * Consulter un article, avec ses soldes.
     */
    public function show(Request $request, StockItem $stockItem): JsonResponse
    {
        $this->guardItem($stockItem);
        $this->authorize('view', $stockItem);

        return ApiResponse::ok(new StockItemDetailResource(
            $stockItem->load(['customer', 'balances.stockLocation']),
        ));
    }

    /**
     * Modifier un article.
     *
     * Permission requise : `stock_items.update`. Le client n'est pas
     * modifiable.
     */
    public function update(UpdateStockItemRequest $request, StockItem $stockItem, UpdateStockItemAction $action): JsonResponse
    {
        $organizationId = $this->guardItem($stockItem);
        $this->authorize('update', $stockItem);

        $updated = $action->execute(
            $stockItem,
            UpdateStockItemData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new StockItemDetailResource($updated));
    }

    /**
     * Supprimer un article inutilisé.
     *
     * Permission requise : `stock_items.delete`. Refusé en 409 s'il porte du
     * stock, un mouvement ou une réservation.
     *
     * @response 204
     */
    public function destroy(Request $request, StockItem $stockItem, DeleteStockItemAction $action): JsonResponse
    {
        $organizationId = $this->guardItem($stockItem);
        $this->authorize('delete', $stockItem);

        try {
            $action->execute($stockItem, $this->auditContext($request, $organizationId));
        } catch (StockConflict $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    /**
     * Articles de stock d'un client.
     */
    public function byCustomer(ListStockItemRequest $request, Customer $customer, StockItemListQuery $query): JsonResponse
    {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('viewAny', [StockItem::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['customer_id' => $customer->id]));
    }

    /**
     * Créer un article pour le client de l'URL.
     */
    public function storeForCustomer(StoreStockItemRequest $request, Customer $customer, CreateStockItemAction $action): JsonResponse
    {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('create', [StockItem::class, $organizationId]);

        $item = $action->execute(
            CreateStockItemData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new StockItemDetailResource($item));
    }

    private function respond(mixed $paginator): JsonResponse
    {
        return ApiResponse::paginated($paginator->through(fn (StockItem $i) => new StockItemListResource($i)));
    }

    private function guardItem(StockItem $item): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($item->customer?->organization_id === $organizationId, 404, 'Article introuvable.');

        return $organizationId;
    }

    private function guardCustomer(Customer $customer): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($customer->organization_id === $organizationId, 404, 'Client introuvable.');

        return $organizationId;
    }
}
