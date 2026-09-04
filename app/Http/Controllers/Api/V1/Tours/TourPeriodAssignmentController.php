<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tours;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTourScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tours\StoreTourPeriodAssignmentRequest;
use App\Http\Requests\Api\V1\Tours\UpdateTourPeriodAssignmentRequest;
use App\Http\Resources\Api\V1\Tours\TourPeriodAssignmentResource;
use App\Modules\Tours\Actions\CreateTourPeriodAssignmentAction;
use App\Modules\Tours\Actions\DeleteTourPeriodAssignmentAction;
use App\Modules\Tours\Actions\UpdateTourPeriodAssignmentAction;
use App\Modules\Tours\DTOs\CreateTourPeriodAssignmentData;
use App\Modules\Tours\DTOs\UpdateTourPeriodAssignmentData;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Affectations d'une période.
 */
class TourPeriodAssignmentController extends Controller
{
    use BuildsAuditContext;
    use ResolvesTourScope;

    /**
     * Lister les affectations d'une période.
     *
     * Permission requise : `tour_period_assignments.view`.
     */
    public function index(ListRequest $request, Tour $tour, TourPeriod $tourPeriod): JsonResponse
    {
        $organizationId = $this->guardPeriod($tour, $tourPeriod);
        $this->authorize('viewAny', [TourPeriodAssignment::class, $organizationId]);

        $paginator = $tourPeriod->assignments()
            ->with('tourStopService')
            ->paginate($request->getPerPage());

        return ApiResponse::paginated(
            $paginator->through(fn (TourPeriodAssignment $a) => new TourPeriodAssignmentResource($a)),
        );
    }

    /**
     * Créer une affectation.
     *
     * Permission requise : `tour_period_assignments.create`. Le service doit
     * relever d'un arrêt de la même tournée ; le colis, s'il est fourni, de la
     * commande du service.
     */
    public function store(StoreTourPeriodAssignmentRequest $request, Tour $tour, TourPeriod $tourPeriod, CreateTourPeriodAssignmentAction $action): JsonResponse
    {
        $organizationId = $this->guardPeriod($tour, $tourPeriod);
        $this->guardDraftOwner($tour);
        $this->authorize('create', [TourPeriodAssignment::class, $organizationId]);

        $assignment = $action->execute(
            $tourPeriod,
            CreateTourPeriodAssignmentData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new TourPeriodAssignmentResource($assignment));
    }

    /**
     * Consulter une affectation.
     *
     * Permission requise : `tour_period_assignments.view`.
     */
    public function show(Request $request, Tour $tour, TourPeriod $tourPeriod, TourPeriodAssignment $assignment): JsonResponse
    {
        $this->guardAssignment($tour, $tourPeriod, $assignment);
        $this->authorize('view', $assignment);

        return ApiResponse::ok(new TourPeriodAssignmentResource($assignment->load('tourStopService')));
    }

    /**
     * Modifier une affectation.
     *
     * Permission requise : `tour_period_assignments.update`.
     */
    public function update(UpdateTourPeriodAssignmentRequest $request, Tour $tour, TourPeriod $tourPeriod, TourPeriodAssignment $assignment, UpdateTourPeriodAssignmentAction $action): JsonResponse
    {
        $organizationId = $this->guardAssignment($tour, $tourPeriod, $assignment);
        $this->guardDraftOwner($tour);
        $this->authorize('update', $assignment);

        $updated = $action->execute(
            $assignment,
            UpdateTourPeriodAssignmentData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new TourPeriodAssignmentResource($updated));
    }

    /**
     * Supprimer une affectation.
     *
     * Permission requise : `tour_period_assignments.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Tour $tour, TourPeriod $tourPeriod, TourPeriodAssignment $assignment, DeleteTourPeriodAssignmentAction $action): JsonResponse
    {
        $organizationId = $this->guardAssignment($tour, $tourPeriod, $assignment);
        $this->guardDraftOwner($tour);
        $this->authorize('delete', $assignment);

        $action->execute($assignment, $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }
}
