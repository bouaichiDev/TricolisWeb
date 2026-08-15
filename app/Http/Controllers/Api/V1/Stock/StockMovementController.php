<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Stock\ListStockMovementRequest;
use App\Http\Requests\Api\V1\Stock\StoreStockMovementRequest;
use App\Http\Resources\Api\V1\Stock\StockMovementDetailResource;
use App\Http\Resources\Api\V1\Stock\StockMovementListResource;
use App\Modules\Stock\Actions\CreateStockMovementAction;
use App\Modules\Stock\DTOs\CreateStockMovementData;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Queries\StockMovementListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mouvements de stock.
 *
 * Ni `update`, ni `destroy` : un mouvement est historique. Une correction est
 * un nouveau mouvement.
 */
class StockMovementController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les mouvements.
     *
     * Permission requise : `stock_movements.view`. Ordre par défaut :
     * `created_at` décroissant.
     */
    public function index(ListStockMovementRequest $request, StockMovementListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [StockMovement::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated($paginator->through(fn (StockMovement $m) => new StockMovementListResource($m)));
    }

    /**
     * Créer un mouvement et déplacer les quantités.
     *
     * Permission requise : `stock_movements.create`. Au moins une source ou une
     * destination ; les deux doivent différer et relever du même dépôt. Un
     * débit supérieur au disponible est refusé en 409.
     */
    public function store(StoreStockMovementRequest $request, CreateStockMovementAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [StockMovement::class, $organizationId]);

        try {
            $movement = $action->execute(
                CreateStockMovementData::fromValidated($request->validated()),
                $this->auditContext($request, $organizationId),
                now()->toDateTimeString(),
            );
        } catch (StockConflict $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::created(new StockMovementDetailResource($movement));
    }

    /**
     * Consulter un mouvement.
     *
     * Permission requise : `stock_movements.view`.
     */
    public function show(Request $request, StockMovement $stockMovement): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless(
            $stockMovement->stockItem?->customer?->organization_id === $organizationId,
            404,
            'Mouvement introuvable.',
        );
        $this->authorize('view', $stockMovement);

        return ApiResponse::ok(new StockMovementDetailResource(
            $stockMovement->load(['stockItem', 'sourceLocation', 'destinationLocation', 'creator']),
        ));
    }
}
