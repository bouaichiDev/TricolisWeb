<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Stock\ListStockBalanceRequest;
use App\Http\Resources\Api\V1\Stock\StockBalanceDetailResource;
use App\Http\Resources\Api\V1\Stock\StockBalanceListResource;
use App\Modules\Customers\Models\Customer;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Queries\StockBalanceListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Soldes de stock — **lecture seule**.
 *
 * Ni `store`, ni `update`, ni `destroy` : le §14 interdit un CRUD public qui
 * permettrait de fixer arbitrairement les quantités. Les soldes ne bougent que
 * par les mouvements et les réservations, sous verrou.
 */
class StockBalanceController extends Controller
{
    /**
     * Lister les soldes de l'organisation active.
     *
     * Permission requise : `stock_balances.view`. `availableOnly=1` ne retourne
     * que ce qui est réellement disponible.
     */
    public function index(ListStockBalanceRequest $request, StockBalanceListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [StockBalance::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId));
    }

    /**
     * Consulter un solde.
     *
     * Permission requise : `stock_balances.view`.
     */
    public function show(Request $request, StockBalance $stockBalance): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless(
            $stockBalance->stockItem?->customer?->organization_id === $organizationId,
            404,
            'Solde introuvable.',
        );
        $this->authorize('view', $stockBalance);

        return ApiResponse::ok(new StockBalanceDetailResource(
            $stockBalance->load(['stockItem', 'stockLocation']),
        ));
    }

    /**
     * Soldes d'un client, tous emplacements confondus.
     */
    public function byCustomer(ListStockBalanceRequest $request, Customer $customer, StockBalanceListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($customer->organization_id === $organizationId, 404, 'Client introuvable.');
        $this->authorize('viewAny', [StockBalance::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated(
            $paginator->through(fn (StockBalance $b) => new StockBalanceListResource($b)),
        );
    }

    private function respond(mixed $paginator): JsonResponse
    {
        return ApiResponse::paginated(
            $paginator->through(fn (StockBalance $b) => new StockBalanceListResource($b)),
        );
    }
}
