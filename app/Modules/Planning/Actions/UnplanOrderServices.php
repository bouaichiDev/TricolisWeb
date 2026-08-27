<?php

declare(strict_types=1);

namespace App\Modules\Planning\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Planning\Jobs\RecalculateTourRouteJob;
use App\Modules\Planning\Services\StopSequence;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStopService;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Retire des services d'une tournée et les rend au pool.
 *
 * Symétrique de {@see PlanOrderServices} : un appel pour tout un geste, les
 * retirables passent, les autres sont nommés.
 *
 * **Le sort de l'affectation dépend de l'état de la tournée**, comme le §115
 * le laisse décider au serveur :
 *
 * - tournée **brouillon** — la ligne est supprimée. Rien ne s'est encore
 *   passé sur le terrain, et le §115 autorise expressément la suppression
 *   quand aucun historique n'est nécessaire ;
 * - tournée **confirmée ou partie** — l'affectation est **désactivée**, pas
 *   effacée. Le §31 en fait la mémoire du parcours d'un service, et la fiche
 *   de la tournée doit continuer de montrer ce qui lui a appartenu.
 *
 * Dans les deux cas le service repasse à « prêt à planifier » : c'est ce qui
 * le fait réapparaître dans le pool. Le référentiel ne décrit aucune
 * transition pour les services de commande — c'est la planification qui pose
 * leur statut, et la déplanification qui le reprend.
 *
 * Une tournée **terminée** ne se déplanifie pas : ce qui a été livré ne
 * retourne pas dans le pool. C'est la règle posée par le propriétaire du
 * projet le 26 août 2026.
 */
final readonly class UnplanOrderServices
{
    /** Motif rendu quand le service n'est pas porté par cette tournée. */
    public const string REASON_NOT_PLANNED = 'not_planned';

    public function __construct(
        private TourTotals $totals,
        private StopSequence $sequence,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @param  list<string>  $orderServiceIds
     * @return array{unplanned: list<string>, rejected: list<array{orderServiceId: string, reason: string}>}
     */
    public function execute(Tour $tour, array $orderServiceIds, AuditContext $context): array
    {
        if ($orderServiceIds === []) {
            return ['unplanned' => [], 'rejected' => []];
        }

        return DB::transaction(function () use ($tour, $orderServiceIds, $context): array {
            $targets = $this->wholeOrders($tour, $orderServiceIds);

            // Verrouillees le temps du retrait : un autre planificateur ne doit
            // pas replanifier ailleurs un service qu'on est en train de rendre.
            $assignments = TourStopService::query()
                ->whereIn('order_service_id', $targets)
                ->where('is_active_assignment', true)
                ->whereHas('tourStop', fn ($stop) => $stop->where('tour_id', $tour->id))
                ->with('tourStop')
                ->lockForUpdate()
                ->get()
                ->keyBy('order_service_id');

            $unplanned = [];
            $rejected = [];
            $touchedStops = [];

            foreach ($targets as $id) {
                $assignment = $assignments->get($id);

                if ($assignment === null) {
                    $rejected[] = ['orderServiceId' => $id, 'reason' => self::REASON_NOT_PLANNED];

                    continue;
                }

                $touchedStops[$assignment->tour_stop_id] = true;
                $this->release($tour, $assignment);
                $unplanned[] = $id;
            }

            if ($unplanned !== []) {
                // Hors brouillon, l'arret reste : supprimer le porteur
                // emporterait par cascade l'historique qu'on vient tout juste
                // de preserver en desactivant.
                if ($tour->status->value === 'draft') {
                    $this->sequence->pruneAndCompact($tour, array_keys($touchedStops));
                }

                $this->totals->recalculate($tour);

                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'tour.services_unplanned',
                    $tour,
                    null,
                    ['unplanned' => $unplanned],
                    null,
                    $context->ipAddress,
                );

                // La composition a change : l'itineraire connu decrit un ordre
                // qui n'existe plus. Le calcul part en file, apres la
                // transaction, pour ne pas faire attendre le depot.
                RecalculateTourRouteJob::dispatch($tour->id)->afterCommit();
            }

            return ['unplanned' => $unplanned, 'rejected' => $rejected];
        });
    }

    /**
     * Étend la demande à toute la commande, dans cette tournée.
     *
     * **Retirer un service retire ses frères**, règle posée par le propriétaire
     * du projet le 27 août 2026. C'est la symétrique du §40 : glisser une
     * commande prend tous ses services éligibles, la retirer les rend tous.
     *
     * Sans cela, retirer la livraison laisserait le chargement au dépôt — un
     * arrêt où le camion charge ce que personne n'ira livrer.
     *
     * L'extension s'arrête à **cette** tournée : une commande répartie sur deux
     * tournées ne perd que sa part ici.
     *
     * @param  list<string>  $orderServiceIds
     * @return list<string>
     */
    private function wholeOrders(Tour $tour, array $orderServiceIds): array
    {
        $orderIds = OrderService::whereIn('id', $orderServiceIds)->pluck('order_id')->unique();

        if ($orderIds->isEmpty()) {
            // Aucun service connu : les identifiants restent tels quels, pour
            // que chacun rende son refus « non planifie ».
            return array_values($orderServiceIds);
        }

        $siblings = OrderService::whereIn('order_id', $orderIds)
            ->whereHas('tourStopServices', fn ($assignments) => $assignments
                ->where('is_active_assignment', true)
                ->whereHas('tourStop', fn ($stop) => $stop->where('tour_id', $tour->id)))
            ->orderBy('sequence')
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($orderServiceIds, $siblings)));
    }

    /** Rend le service au pool, en gardant ou non la trace de son passage. */
    private function release(Tour $tour, TourStopService $assignment): void
    {
        if ($tour->status->value === 'draft') {
            $assignment->delete();
        } else {
            $assignment->forceFill(['is_active_assignment' => false])->save();
        }

        $assignment->orderService?->forceFill([
            'status' => OrderServiceStatus::READY_TO_PLAN->value,
        ])->save();
    }
}
