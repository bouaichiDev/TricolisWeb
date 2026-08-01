<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tours;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTourScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tours\ReorderRequest;
use App\Http\Requests\Api\V1\Tours\StoreTourStopServiceRequest;
use App\Http\Requests\Api\V1\Tours\UpdateTourStopServiceRequest;
use App\Http\Resources\Api\V1\Tours\TourStopServiceResource;
use App\Modules\Tours\Actions\AssignOrderServiceToTourStopAction;
use App\Modules\Tours\Actions\DeleteTourStopServiceAction;
use App\Modules\Tours\Actions\ReorderTourStopServicesAction;
use App\Modules\Tours\Actions\UpdateTourStopServiceAction;
use App\Modules\Tours\DTOs\CreateTourStopServiceData;
use App\Modules\Tours\DTOs\UpdateTourStopServiceData;
use App\Modules\Tours\Exceptions\TourResourceStillInUse;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Services de commande planifiés sur un arrêt.
 */
class TourStopServiceController extends Controller
{
    use BuildsAuditContext;
    use ResolvesTourScope;

    /**
     * Lister les services d'un arrêt, actifs comme historiques.
     *
     * Permission requise : `tour_stop_services.view`.
     */
    public function index(ListRequest $request, Tour $tour, TourStop $tourStop): JsonResponse
    {
        $organizationId = $this->guardStop($tour, $tourStop);
        $this->authorize('viewAny', [TourStopService::class, $organizationId]);

        $paginator = $tourStop->services()
            ->with('orderService:id,service_number')
            ->withCount('assignments')
            ->orderBy('sequence_within_stop')
            ->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (TourStopService $s) => new TourStopServiceResource($s)));
    }

    /**
     * Planifier un service sur l'arrêt.
     *
     * Permission requise : `tour_stop_services.create`. Le service doit venir
     * d'une commande de l'organisation de la tournée.
     */
    public function store(StoreTourStopServiceRequest $request, Tour $tour, TourStop $tourStop, AssignOrderServiceToTourStopAction $action): JsonResponse
    {
        $organizationId = $this->guardStop($tour, $tourStop);
        $this->authorize('create', [TourStopService::class, $organizationId]);

        $service = $action->execute(
            $tourStop,
            CreateTourStopServiceData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new TourStopServiceResource($service));
    }

    /**
     * Consulter un service planifié.
     *
     * Permission requise : `tour_stop_services.view`.
     */
    public function show(Request $request, Tour $tour, TourStop $tourStop, TourStopService $tourStopService): JsonResponse
    {
        $this->guardStopService($tour, $tourStop, $tourStopService);
        $this->authorize('view', $tourStopService);

        return ApiResponse::ok(new TourStopServiceResource(
            $tourStopService->load('orderService')->loadCount('assignments'),
        ));
    }

    /**
     * Modifier un service planifié, ou le désactiver.
     *
     * Permission requise : `tour_stop_services.update`. Passer
     * `isActiveAssignment` à `false` conserve la ligne : c'est ainsi que
     * l'historique des affectations se construit. Désactiver le dernier service
     * actif d'un arrêt est refusé en 409.
     */
    public function update(UpdateTourStopServiceRequest $request, Tour $tour, TourStop $tourStop, TourStopService $tourStopService, UpdateTourStopServiceAction $action): JsonResponse
    {
        $organizationId = $this->guardStopService($tour, $tourStop, $tourStopService);
        $this->authorize('update', $tourStopService);

        try {
            $updated = $action->execute(
                $tourStopService,
                UpdateTourStopServiceData::fromValidated($request->validated()),
                $this->auditContext($request, $organizationId),
            );
        } catch (TourResourceStillInUse $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::ok(new TourStopServiceResource($updated));
    }

    /**
     * Retirer un service planifié.
     *
     * Permission requise : `tour_stop_services.delete`. Refusé en 409 s'il est
     * affecté à une période, ou s'il est le dernier service actif de l'arrêt.
     *
     * @response 204
     */
    public function destroy(Request $request, Tour $tour, TourStop $tourStop, TourStopService $tourStopService, DeleteTourStopServiceAction $action): JsonResponse
    {
        $organizationId = $this->guardStopService($tour, $tourStop, $tourStopService);
        $this->authorize('delete', $tourStopService);

        try {
            $action->execute($tourStopService, $this->auditContext($request, $organizationId));
        } catch (TourResourceStillInUse $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    /**
     * Réordonner les services d'un arrêt.
     *
     * Permission requise : `tour_stop_services.reorder`.
     *
     * @response 204
     */
    public function reorder(ReorderRequest $request, Tour $tour, TourStop $tourStop, ReorderTourStopServicesAction $action): JsonResponse
    {
        $organizationId = $this->guardStop($tour, $tourStop);
        $this->authorize('reorder', [TourStopService::class, $organizationId]);

        $action->execute($tourStop, $request->orderedIds(), $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }
}
