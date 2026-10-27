<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Stock\ListCustomerStockRequest;
use App\Http\Requests\Api\V1\Customer\Stock\StoreCustomerStockAdjustmentRequest;
use App\Http\Resources\Api\V1\Customer\Stock\CustomerStockAdjustmentRequestResource;
use App\Modules\CustomerPortal\Actions\CreateCustomerStockAdjustmentRequest;
use App\Modules\CustomerPortal\Services\Stock\CustomerStockScope;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Demandes de correction de stock du client (§19, §21).
 *
 * Lire : `customer.stock.adjustment_requests.view`. Déposer :
 * `customer.stock.adjustment_requests.create`. Aucune route ne modifie une
 * demande ni le stock : la décision appartient au transporteur.
 */
class CustomerStockAdjustmentRequestController extends Controller
{
    private const array RELATIONS = [
        'stockItem:id,article_code,description',
        'stockLocation:id,depot_id,zone_code,aisle,rack,level,location_code',
        'stockLocation.depot:id,name',
    ];

    public function __construct(private readonly CustomerStockScope $scope) {}

    public function index(ListCustomerStockRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->requests()
            ->when($filters['stockItemId'] ?? null, static fn (Builder $query, string $id) => $query->where('stock_item_id', $id))
            ->when($filters['status'] ?? null, static fn (Builder $query, mixed $status) => $query->whereIn('status', (array) $status))
            ->latest('created_at')->orderByDesc('id')
            ->paginate((int) ($filters['perPage'] ?? 25));

        return ApiResponse::paginated($page->through(static fn (StockAdjustmentRequest $adjustment) => new CustomerStockAdjustmentRequestResource($adjustment)));
    }

    public function show(string $stockAdjustmentRequest): JsonResponse
    {
        return ApiResponse::ok(new CustomerStockAdjustmentRequestResource($this->scope->find($this->requests(), $stockAdjustmentRequest)));
    }

    /** 201 ; 409 si une demande attend déjà pour cet article et cet emplacement. */
    public function store(StoreCustomerStockAdjustmentRequest $request, CreateCustomerStockAdjustmentRequest $create): JsonResponse
    {
        try {
            $adjustment = $create->execute($request->validated(), $request);
        } catch (StockConflict $conflict) {
            return ApiResponse::error($conflict->getMessage(), 409);
        }

        return ApiResponse::created(new CustomerStockAdjustmentRequestResource($adjustment->load(self::RELATIONS)));
    }

    /**
     * @return Builder<StockAdjustmentRequest>
     */
    private function requests(): Builder
    {
        return $this->scope->adjustmentRequests()->with(self::RELATIONS);
    }
}
