<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tours;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTourScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tours\ReorderRequest;
use App\Http\Requests\Api\V1\Tours\StoreTourStopRequest;
use App\Http\Requests\Api\V1\Tours\UpdateTourStopRequest;
use App\Http\Resources\Api\V1\Tours\TourStopDetailResource;
use App\Http\Resources\Api\V1\Tours\TourStopResource;
use App\Modules\Tours\Actions\CreateTourStopAction;
use App\Modules\Tours\Actions\DeleteTourStopAction;
use App\Modules\Tours\Actions\ReorderTourStopsAction;
use App\Modules\Tours\Actions\UpdateTourStopAction;
use App\Modules\Tours\DTOs\CreateTourStopData;
use App\Modules\Tours\DTOs\UpdateTourStopData;
use App\Modules\Tours\Exceptions\TourResourceStillInUse;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Arrêts d'une tournée.
 */
class TourStopController extends Controller
{
    use BuildsAuditContext;
    use ResolvesTourScope;

    /**
     * Lister les arrêts d'une tournée, par séquence croissante.
     *
     * Permission requise : `tour_stops.view`.
     */
    public function index(ListRequest $request, Tour $tour): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->authorize('viewAny', [TourStop::class, $organizationId]);

        $paginator = $tour->stops()
            ->withCount('services')
            ->orderBy('sequence')
            ->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (TourStop $s) => new TourStopResource($s)));
    }

    /**
     * Créer un arrêt avec ses services.
     *
     * Permission requise : `tour_stops.create`. Au moins un service est exigé :
     * la cardinalité `1..*` du diagramme interdit un arrêt vide. L'arrêt et ses
     * services sont écrits dans la même transaction.
     */
    public function store(StoreTourStopRequest $request, Tour $tour, CreateTourStopAction $action): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->guardDraftOwner($tour);
        $this->authorize('create', [TourStop::class, $organizationId]);

        $stop = $action->execute(
            $tour,
            CreateTourStopData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new TourStopDetailResource($stop->load('services')));
    }

    /**
     * Consulter un arrêt.
     *
     * Permission requise : `tour_stops.view`. Un arrêt d'une autre tournée
     * renvoie 404.
     */
    public function show(Request $request, Tour $tour, TourStop $tourStop): JsonResponse
    {
        $this->guardStop($tour, $tourStop);
        $this->authorize('view', $tourStop);

        return ApiResponse::ok(new TourStopDetailResource($tourStop->load(['address', 'services'])));
    }

    /**
     * Modifier un arrêt.
     *
     * Permission requise : `tour_stops.update`.
     */
    public function update(UpdateTourStopRequest $request, Tour $tour, TourStop $tourStop, UpdateTourStopAction $action): JsonResponse
    {
        $organizationId = $this->guardStop($tour, $tourStop);
        $this->guardDraftOwner($tour);
        $this->authorize('update', $tourStop);

        $updated = $action->execute(
            $tourStop,
            UpdateTourStopData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new TourStopDetailResource($updated));
    }

    /**
     * Supprimer un arrêt et ses services.
     *
     * Permission requise : `tour_stops.delete`. Refusé en 409 si des périodes
     * le référencent, ou si l'un de ses services est déjà affecté.
     *
     * @response 204
     */
    public function destroy(Request $request, Tour $tour, TourStop $tourStop, DeleteTourStopAction $action): JsonResponse
    {
        $organizationId = $this->guardStop($tour, $tourStop);
        $this->guardDraftOwner($tour);
        $this->authorize('delete', $tourStop);

        try {
            $action->execute($tourStop, $this->auditContext($request, $organizationId));
        } catch (TourResourceStillInUse $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    /**
     * Réordonner les arrêts.
     *
     * Permission requise : `tour_stops.reorder`. La liste doit contenir tous
     * les arrêts de la tournée, une fois chacun ; les séquences sont réécrites
     * de 1 à N dans une transaction.
     *
     * @response 204
     */
    public function reorder(ReorderRequest $request, Tour $tour, ReorderTourStopsAction $action): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->guardDraftOwner($tour);
        $this->authorize('reorder', [TourStop::class, $organizationId]);

        $action->execute($tour, $request->orderedIds(), $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }
}
