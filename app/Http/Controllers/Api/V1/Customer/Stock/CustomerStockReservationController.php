<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Stock\ListCustomerStockRequest;
use App\Http\Resources\Api\V1\Customer\Stock\CustomerStockReservationResource;
use App\Modules\CustomerPortal\Queries\Stock\CustomerStockReservationsQuery;
use App\Modules\CustomerPortal\Services\Stock\CustomerStockScope;
use App\Modules\Stock\Models\StockReservation;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Réservations des articles du client pour ses commandes (§17) — lecture
 * seule. Permission : `customer.stock.reservations.view`. Ni création ni
 * libération : elles restent aux Actions internes.
 */
class CustomerStockReservationController extends Controller
{
    public function __construct(private readonly CustomerStockReservationsQuery $reservations) {}

    public function index(ListCustomerStockRequest $request): JsonResponse
    {
        $page = $this->reservations->paginate($request->validated());

        return ApiResponse::paginated($page->through(static fn (StockReservation $reservation) => new CustomerStockReservationResource($reservation)));
    }

    public function show(string $stockReservation, CustomerStockScope $scope): JsonResponse
    {
        return ApiResponse::ok(new CustomerStockReservationResource($scope->find($this->reservations->one(), $stockReservation)));
    }
}
