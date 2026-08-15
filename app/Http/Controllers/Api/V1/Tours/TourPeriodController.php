<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tours;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTourScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tours\ListTourPeriodRequest;
use App\Http\Requests\Api\V1\Tours\ReorderRequest;
use App\Http\Requests\Api\V1\Tours\StoreTourPeriodRequest;
use App\Http\Requests\Api\V1\Tours\UpdateTourPeriodRequest;
use App\Http\Resources\Api\V1\Tours\TourPeriodDetailResource;
use App\Http\Resources\Api\V1\Tours\TourPeriodResource;
use App\Modules\Tours\Actions\CreateTourPeriodAction;
use App\Modules\Tours\Actions\DeleteTourPeriodAction;
use App\Modules\Tours\Actions\ReorderTourPeriodsAction;
use App\Modules\Tours\Actions\UpdateTourPeriodAction;
use App\Modules\Tours\DTOs\CreateTourPeriodData;
use App\Modules\Tours\DTOs\UpdateTourPeriodData;
use App\Modules\Tours\Exceptions\TourResourceStillInUse;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Queries\TourPeriodListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Périodes d'une tournée.
 */
class TourPeriodController extends Controller
{
    use BuildsAuditContext;
    use ResolvesTourScope;

    /**
     * Lister les périodes.
     *
     * Permission requise : `tour_periods.view`. Filtres `tourStopId`,
     * `periodType`, `status`, `plannedFrom`, `plannedTo`, `actualFrom`,
     * `actualTo`.
     */
    public function index(ListTourPeriodRequest $request, Tour $tour, TourPeriodListQuery $query): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->authorize('viewAny', [TourPeriod::class, $organizationId]);

        $paginator = $query->paginate($request, $tour);

        return ApiResponse::paginated($paginator->through(fn (TourPeriod $p) => new TourPeriodResource($p)));
    }

    /**
     * Créer une période.
     *
     * Permission requise : `tour_periods.create`. L'arrêt est facultatif ;
     * fourni, il doit appartenir à cette tournée.
     */
    public function store(StoreTourPeriodRequest $request, Tour $tour, CreateTourPeriodAction $action): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->authorize('create', [TourPeriod::class, $organizationId]);

        $period = $action->execute(
            $tour,
            CreateTourPeriodData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new TourPeriodDetailResource($period));
    }

    /**
     * Consulter une période.
     *
     * Permission requise : `tour_periods.view`.
     */
    public function show(Request $request, Tour $tour, TourPeriod $tourPeriod): JsonResponse
    {
        $this->guardPeriod($tour, $tourPeriod);
        $this->authorize('view', $tourPeriod);

        return ApiResponse::ok(new TourPeriodDetailResource(
            $tourPeriod->load(['tourStop', 'assignments'])->loadCount('assignments'),
        ));
    }

    /**
     * Modifier une période.
     *
     * Permission requise : `tour_periods.update`.
     */
    public function update(UpdateTourPeriodRequest $request, Tour $tour, TourPeriod $tourPeriod, UpdateTourPeriodAction $action): JsonResponse
    {
        $organizationId = $this->guardPeriod($tour, $tourPeriod);
        $this->authorize('update', $tourPeriod);

        $updated = $action->execute(
            $tourPeriod,
            UpdateTourPeriodData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new TourPeriodDetailResource($updated));
    }

    /**
     * Supprimer une période.
     *
     * Permission requise : `tour_periods.delete`. Refusée en 409 si elle porte
     * encore des affectations.
     *
     * @response 204
     */
    public function destroy(Request $request, Tour $tour, TourPeriod $tourPeriod, DeleteTourPeriodAction $action): JsonResponse
    {
        $organizationId = $this->guardPeriod($tour, $tourPeriod);
        $this->authorize('delete', $tourPeriod);

        try {
            $action->execute($tourPeriod, $this->auditContext($request, $organizationId));
        } catch (TourResourceStillInUse $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    /**
     * Réordonner les périodes.
     *
     * Permission requise : `tour_periods.reorder`.
     *
     * @response 204
     */
    public function reorder(ReorderRequest $request, Tour $tour, ReorderTourPeriodsAction $action): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->authorize('reorder', [TourPeriod::class, $organizationId]);

        $action->execute($tour, $request->orderedIds(), $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }
}
