<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tours;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTourScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tours\ChangeTourStatusRequest;
use App\Http\Requests\Api\V1\Tours\PlanServicesRequest;
use App\Http\Resources\Api\V1\Tours\TourDetailResource;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Planning\Actions\ChangeTourStatus;
use App\Modules\Planning\Actions\PlanOrderServices;
use App\Modules\Planning\Actions\UnplanOrderServices;
use App\Modules\Tours\Models\Tour;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Ce qu'on fait à une tournée sans la modifier elle-même : y verser des
 * commandes, les en retirer, la faire changer d'état, lire son tracé.
 *
 * Séparé du CRUD parce que ce sont deux métiers : l'un tient la fiche d'une
 * tournée, l'autre son contenu. Les garder ensemble donnait un contrôleur de
 * trois cent cinquante lignes où l'on ne trouvait plus rien.
 */
class TourPlanningController extends Controller
{
    use BuildsAuditContext;
    use ResolvesTourScope;

    /**
     * Faire passer une tournée d'un état à un autre.
     *
     * Permission requise : `tours.update`. C'est par ici que se valide un
     * brouillon et qu'il s'annule : le référentiel dit quels passages
     * existent, et l'action les applique dans une transaction, tournée
     * verrouillée.
     *
     * Un brouillon reste réservé à celui qui le prépare, comme toute autre
     * écriture.
     */
    public function changeStatus(
        ChangeTourStatusRequest $request,
        Tour $tour,
        ChangeTourStatus $action,
    ): JsonResponse {
        $organizationId = $this->guardTour($tour);
        $this->guardDraftOwner($tour);
        $this->authorize('update', $tour);

        $updated = $action->execute(
            $tour,
            $request->validated('status'),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new TourDetailResource($updated));
    }

    /**
     * Planifier une commande, ou certains de ses services, dans la tournée.
     *
     * Permission requise : `tours.update`. Les services éligibles entrent, les
     * autres sont rendus avec leur motif : un service déjà livré ne doit pas
     * empêcher de planifier le reste de sa commande.
     *
     * Un seul appel, une seule transaction — glisser une commande de huit
     * services ne doit pas laisser la tournée à mi-chemin.
     */
    public function plan(
        PlanServicesRequest $request,
        Tour $tour,
        PlanOrderServices $action,
    ): JsonResponse {
        $organizationId = $this->guardTour($tour);
        $this->guardDraftOwner($tour);
        $this->authorize('update', $tour);

        $serviceIds = $this->serviceIdsFrom($request, $organizationId);

        $result = $action->execute(
            $tour,
            $serviceIds,
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok([
            'tour' => new TourDetailResource($tour->fresh()->load('stops.services')),
            'planned' => $result['planned'],
            'rejected' => $result['rejected'],
        ]);
    }

    /**
     * Retirer des services d'une tournée et les rendre au pool.
     *
     * Permission requise : `tours.update`.
     *
     * **Une tournée terminée ne se déplanifie pas.** Ce qui a été livré ne
     * retourne pas dans le pool ; tous les autres états acceptent le retrait,
     * y compris une tournée en route dont un client s'annule.
     *
     * Le sort de l'affectation dépend de l'état : effacée dans un brouillon,
     * désactivée ailleurs pour garder trace du passage. Voir
     * {@see UnplanOrderServices}.
     */
    public function unplan(
        PlanServicesRequest $request,
        Tour $tour,
        UnplanOrderServices $action,
    ): JsonResponse {
        $organizationId = $this->guardTour($tour);
        $this->guardDraftOwner($tour);
        $this->authorize('update', $tour);

        abort_if(
            $tour->status->value === 'completed',
            422,
            'Une tournée terminée ne peut plus être déplanifiée.',
        );

        $result = $action->execute(
            $tour,
            $this->plannedServiceIdsFrom($request, $organizationId, $tour),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok([
            'tour' => new TourDetailResource($tour->fresh()->load('stops.services')),
            'unplanned' => $result['unplanned'],
            'rejected' => $result['rejected'],
        ]);
    }

    /**
     * Services à retirer : ceux nommés, plus ceux **que cette tournée porte**
     * parmi les commandes désignées.
     *
     * Une commande n'est pas toujours entièrement dans la même tournée :
     * prendre tous ses services produirait autant de refus « non planifié »
     * que de services restés ailleurs, pour un geste qui a pourtant abouti.
     *
     * @return list<string>
     */
    private function plannedServiceIdsFrom(
        PlanServicesRequest $request,
        string $organizationId,
        Tour $tour,
    ): array {
        $ids = $request->validated('orderServiceIds', []);
        $orderIds = $request->validated('orderIds', []);

        if ($orderIds !== []) {
            $held = OrderService::whereIn('order_id', $orderIds)
                ->whereHas('order', fn ($order) => $order->where('organization_id', $organizationId))
                ->whereHas('tourStopServices', fn ($assignments) => $assignments
                    ->where('is_active_assignment', true)
                    ->whereHas('tourStop', fn ($stop) => $stop->where('tour_id', $tour->id)))
                ->orderBy('sequence')
                ->pluck('id')
                ->all();

            $ids = array_merge($ids, $held);
        }

        return array_values(array_unique($ids));
    }

    /**
     * Services visés : ceux des commandes glissées, plus ceux nommés un à un.
     *
     * Les commandes sont résolues **dans l'organisation active** : un
     * identifiant venu d'ailleurs ne rapporte rien plutôt que d'ouvrir une
     * porte.
     *
     * @return list<string>
     */
    private function serviceIdsFrom(PlanServicesRequest $request, string $organizationId): array
    {
        $ids = $request->validated('orderServiceIds', []);

        $orderIds = $request->validated('orderIds', []);

        if ($orderIds !== []) {
            $fromOrders = OrderService::whereIn('order_id', $orderIds)
                ->whereHas('order', fn ($order) => $order->where('organization_id', $organizationId))
                ->orderBy('sequence')
                ->pluck('id')
                ->all();

            $ids = array_merge($ids, $fromOrders);
        }

        return array_values(array_unique($ids));
    }
}
