<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tours;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTourScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tours\ListTourRequest;
use App\Http\Requests\Api\V1\Tours\StoreTourRequest;
use App\Http\Requests\Api\V1\Tours\UpdateTourRequest;
use App\Http\Resources\Api\V1\Tours\TourDetailResource;
use App\Http\Resources\Api\V1\Tours\TourListResource;
use App\Modules\Planning\Services\DraftOwnership;
use App\Modules\Planning\Services\TourReservation;
use App\Modules\Tours\Actions\CreateTourAction;
use App\Modules\Tours\Actions\DeleteTourAction;
use App\Modules\Tours\Actions\UpdateTourAction;
use App\Modules\Tours\DTOs\CreateTourData;
use App\Modules\Tours\DTOs\UpdateTourData;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Queries\TourListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tournées de l'organisation active.
 */
class TourController extends Controller
{
    use BuildsAuditContext;
    use ResolvesTourScope;

    /**
     * Lister les tournées.
     *
     * Permission requise : `tours.view`. Recherche sur `tour_number` et
     * `instructions` ; filtres `agencyId`, `depotId`, `providerId`, `driverId`,
     * `vehicleId`, `tourDate`, `tourDateFrom`, `tourDateTo`, `tourType`,
     * `status`.
     */
    public function index(ListTourRequest $request, TourListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Tour::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        // Une seule requete pour toute la page : le §23 interdit une colonne
        // `lockedBy`, et poser la question tournee par tournee ferait ce que le
        // budget de requetes de la phase 4 avait deja fait echouer une fois.
        $planners = app(DraftOwnership::class)->namedFor($paginator->items());
        $holders = app(TourReservation::class)->holdersOf($paginator->items());

        return ApiResponse::paginated(
            $paginator->through(fn (Tour $t) => new TourListResource($t, $planners, $holders)),
        );
    }

    /**
     * Créer une tournée.
     *
     * Permission requise : `tours.create`. `tourNumber` est fourni par
     * l'appelant et unique dans l'organisation : aucune règle de génération
     * n'est définie pour les tournées.
     */
    public function store(StoreTourRequest $request, CreateTourAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Tour::class, $organizationId]);

        $tour = $action->execute(
            CreateTourData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new TourDetailResource($tour));
    }

    /**
     * Consulter une tournée.
     *
     * Permission requise : `tours.view`. Les arrêts et périodes ne sont pas
     * chargés : ils ont leurs propres routes.
     */
    public function show(Request $request, Tour $tour): JsonResponse
    {
        $this->guardTour($tour);
        $this->authorize('view', $tour);

        return ApiResponse::ok(new TourDetailResource(
            $tour->load(['provider', 'driver', 'vehicle'])->loadCount(['stops', 'periods']),
        ));
    }

    /**
     * Modifier une tournée.
     *
     * Permission requise : `tours.update`. Le statut se change ici — convention
     * des Phases 2 et 3 — et produit une entrée d'audit dédiée.
     */
    public function update(UpdateTourRequest $request, Tour $tour, UpdateTourAction $action): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->guardDraftOwner($tour);
        $this->authorize('update', $tour);

        $updated = $action->execute(
            $tour,
            UpdateTourData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new TourDetailResource($updated));
    }

    /**
     * Supprimer une tournée et tout son agrégat.
     *
     * Permission requise : `tours.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Tour $tour, DeleteTourAction $action): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->guardDraftOwner($tour);
        $this->authorize('delete', $tour);

        $action->execute($tour, $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }
}
