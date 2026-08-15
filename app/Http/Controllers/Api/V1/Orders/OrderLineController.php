<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\StoreOrderLineRequest;
use App\Http\Requests\Api\V1\Orders\UpdateOrderLineRequest;
use App\Http\Resources\Api\V1\Orders\OrderLineResource;
use App\Modules\Orders\Actions\RecalculateOrderTotals;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Services\OrderScopeGuard;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lignes d'une commande.
 *
 * Une ligne provient soit d'un article de catalogue, soit d'une saisie
 * manuelle. Dans le premier cas les données de l'article sont recopiées : la
 * commande ne dépend plus des modifications ultérieures du catalogue.
 */
class OrderLineController extends Controller
{
    use ResolvesOrderScope;

    /** @var array<string, string> */
    private const array MAPPING = [
        'external_reference' => 'externalReference', 'article_code' => 'articleCode', 'barcode' => 'barcode',
        'name' => 'name', 'description' => 'description', 'quantity' => 'quantity',
        'weight' => 'weight', 'volume' => 'volume', 'length' => 'length', 'width' => 'width', 'height' => 'height',
        'purchase_price' => 'purchasePrice', 'selling_price' => 'sellingPrice', 'status' => 'status',
    ];

    public function __construct(
        private readonly OrderScopeGuard $scope,
        private readonly RecalculateOrderTotals $totals,
    ) {}

    /**
     * Lister les lignes d'une commande.
     *
     * Permission requise : `order_lines.view`.
     */
    public function index(ListRequest $request, Order $order): JsonResponse
    {
        $this->guardOrder($order);
        $this->authorize('manageLines', [$order, 'view']);

        $paginator = $order->lines()->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (OrderLine $line) => new OrderLineResource($line)));
    }

    /**
     * Ajouter une ligne à une commande.
     *
     * Permission requise : `order_lines.create`. `catalogItemId` doit désigner
     * un article d'un catalogue actif du client de la commande — un article
     * d'un autre client ou d'une autre organisation est refusé.
     */
    public function store(StoreOrderLineRequest $request, Order $order): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('manageLines', [$order, 'create']);
        $this->assertOrderIsEditable($order);

        $data = $request->validated();
        $attributes = InputMapper::map($data, self::MAPPING);

        if (isset($data['catalogItemId'])) {
            $item = $this->scope->catalogItem($data['catalogItemId'], $order->customer);
            $attributes = array_merge($item->toOrderLineSnapshot(), $attributes);
        }

        $line = $order->lines()->create($attributes);
        $this->totals->execute($order);
        $this->audit($request, $organizationId, 'created', $line, null, $line->toArray());

        return ApiResponse::created(new OrderLineResource($line));
    }

    /**
     * Consulter une ligne.
     *
     * Permission requise : `order_lines.view`.
     */
    public function show(Request $request, Order $order, OrderLine $line): JsonResponse
    {
        $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $line, 'Ligne');
        $this->authorize('manageLines', [$order, 'view']);

        return ApiResponse::ok(new OrderLineResource($line));
    }

    /**
     * Modifier une ligne.
     *
     * Permission requise : `order_lines.update`. Réduire la quantité en dessous
     * de ce qui est déjà réparti dans des colis est refusé en 422.
     */
    public function update(UpdateOrderLineRequest $request, Order $order, OrderLine $line): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $line, 'Ligne');
        $this->authorize('manageLines', [$order, 'update']);
        $this->assertOrderIsEditable($order);

        $data = $request->validated();
        $old = $line->toArray();

        if (isset($data['quantity']) && (float) $data['quantity'] < $line->assignedQuantity()) {
            return ApiResponse::validationError('Les données fournies sont invalides.', [
                'quantity' => ['La quantité ne peut pas être inférieure à ce qui est déjà réparti dans les colis.'],
            ]);
        }

        $line->update(InputMapper::map($data, self::MAPPING));
        $this->totals->execute($order);
        $this->audit($request, $organizationId, 'updated', $line, $old, $line->fresh()->toArray());

        return ApiResponse::ok(new OrderLineResource($line->fresh()));
    }

    /**
     * Supprimer une ligne.
     *
     * Permission requise : `order_lines.delete`. Une ligne encore répartie dans
     * un colis est refusée en 409 : la détacher d'abord rend l'intention
     * explicite.
     *
     * @response 204
     */
    public function destroy(Request $request, Order $order, OrderLine $line): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $line, 'Ligne');
        $this->authorize('manageLines', [$order, 'delete']);
        $this->assertOrderIsEditable($order);

        if ($line->packageOrderLines()->exists()) {
            return ApiResponse::error('Impossible de supprimer une ligne encore répartie dans des colis.', 409);
        }

        $this->audit($request, $organizationId, 'deleted', $line, $line->toArray(), null);
        $line->delete();
        $this->totals->execute($order);

        return ApiResponse::noContent();
    }
}
