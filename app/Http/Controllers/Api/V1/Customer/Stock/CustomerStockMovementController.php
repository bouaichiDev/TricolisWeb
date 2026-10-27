<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Stock\ListCustomerStockRequest;
use App\Http\Resources\Api\V1\Customer\Stock\CustomerStockMovementResource;
use App\Modules\CustomerPortal\Queries\Stock\CustomerStockMovementsQuery;
use App\Modules\CustomerPortal\Services\Stock\CustomerStockScope;
use App\Modules\Stock\Models\StockMovement;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Mouvements des articles du client (§16) — lecture seule.
 * Permission : `customer.stock.movements.view`. Aucune route d'écriture.
 */
class CustomerStockMovementController extends Controller
{
    public function __construct(private readonly CustomerStockMovementsQuery $movements) {}

    public function index(ListCustomerStockRequest $request): JsonResponse
    {
        $page = $this->movements->paginate($request->validated());

        return ApiResponse::paginated($page->through(static fn (StockMovement $movement) => new CustomerStockMovementResource($movement)));
    }

    public function show(string $stockMovement, CustomerStockScope $scope): JsonResponse
    {
        return ApiResponse::ok(new CustomerStockMovementResource($scope->find($this->movements->one(), $stockMovement)));
    }
}
