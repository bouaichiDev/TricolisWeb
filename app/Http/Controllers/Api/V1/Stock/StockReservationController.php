<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Stock\ListStockReservationRequest;
use App\Http\Requests\Api\V1\Stock\ReleaseStockReservationRequest;
use App\Http\Requests\Api\V1\Stock\StoreStockReservationRequest;
use App\Http\Requests\Api\V1\Stock\UpdateStockReservationRequest;
use App\Http\Resources\Api\V1\Stock\StockReservationDetailResource;
use App\Http\Resources\Api\V1\Stock\StockReservationListResource;
use App\Modules\Stock\Actions\CreateStockReservationAction;
use App\Modules\Stock\Actions\ReleaseStockReservationAction;
use App\Modules\Stock\Actions\UpdateStockReservationAction;
use App\Modules\Stock\DTOs\CreateStockReservationData;
use App\Modules\Stock\DTOs\ReleaseStockReservationData;
use App\Modules\Stock\DTOs\UpdateStockReservationData;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockReservation;
use App\Modules\Stock\Queries\StockReservationListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Réservations de stock.
 *
 * Pas de `destroy` : une réservation libérée reste, pour la traçabilité. La
 * libération passe par `POST /{id}/release`.
 */
class StockReservationController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les réservations.
     *
     * Permission requise : `stock_reservations.view`.
     */
    public function index(ListStockReservationRequest $request, StockReservationListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [StockReservation::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated(
            $paginator->through(fn (StockReservation $r) => new StockReservationListResource($r)),
        );
    }

    /**
     * Réserver du stock pour une ligne de commande.
     *
     * Permission requise : `stock_reservations.create`. Le solde est verrouillé
     * puis contrôlé : réserver plus que disponible renvoie 409.
     */
    public function store(StoreStockReservationRequest $request, CreateStockReservationAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [StockReservation::class, $organizationId]);

        try {
            $reservation = $action->execute(
                CreateStockReservationData::fromValidated($request->validated()),
                $this->auditContext($request, $organizationId),
                now()->toDateTimeString(),
            );
        } catch (StockConflict $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::created(new StockReservationDetailResource($reservation));
    }

    /**
     * Consulter une réservation.
     */
    public function show(Request $request, StockReservation $stockReservation): JsonResponse
    {
        $this->guardReservation($stockReservation);
        $this->authorize('view', $stockReservation);

        return ApiResponse::ok(new StockReservationDetailResource(
            $stockReservation->load(['stockItem', 'stockLocation']),
        ));
    }

    /**
     * Modifier le statut d'une réservation.
     *
     * Permission requise : `stock_reservations.update`. La quantité n'est pas
     * modifiable : pour réserver autrement, on libère et on recrée.
     */
    public function update(UpdateStockReservationRequest $request, StockReservation $stockReservation, UpdateStockReservationAction $action): JsonResponse
    {
        $organizationId = $this->guardReservation($stockReservation);
        $this->authorize('update', $stockReservation);

        $updated = $action->execute(
            $stockReservation,
            UpdateStockReservationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new StockReservationDetailResource($updated));
    }

    /**
     * Libérer une réservation et rendre la quantité disponible.
     *
     * Permission requise : `stock_reservations.release`. La réservation n'est
     * pas supprimée ; une double libération renvoie 409.
     */
    public function release(ReleaseStockReservationRequest $request, StockReservation $stockReservation, ReleaseStockReservationAction $action): JsonResponse
    {
        $organizationId = $this->guardReservation($stockReservation);
        $this->authorize('release', $stockReservation);

        try {
            $released = $action->execute(
                $stockReservation,
                ReleaseStockReservationData::fromValidated($request->validated()),
                $this->auditContext($request, $organizationId),
                now()->toDateTimeString(),
            );
        } catch (StockConflict $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::ok(new StockReservationDetailResource($released));
    }

    private function guardReservation(StockReservation $reservation): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless(
            $reservation->stockItem?->customer?->organization_id === $organizationId,
            404,
            'Réservation introuvable.',
        );

        return $organizationId;
    }
}
