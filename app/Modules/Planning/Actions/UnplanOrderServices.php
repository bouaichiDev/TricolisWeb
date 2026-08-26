<?php

declare(strict_types=1);

namespace App\Modules\Planning\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
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
            // Verrouillees le temps du retrait : un autre planificateur ne doit
            // pas replanifier ailleurs un service qu'on est en train de rendre.
            $assignments = TourStopService::query()
                ->whereIn('order_service_id', $orderServiceIds)
                ->where('is_active_assignment', true)
                ->whereHas('tourStop', fn ($stop) => $stop->where('tour_id', $tour->id))
                ->with('tourStop')
                ->lockForUpdate()
                ->get()
                ->keyBy('order_service_id');

            $unplanned = [];
            $rejected = [];
            $touchedStops = [];

            foreach ($orderServiceIds as $id) {
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
                    $this->pruneStops($tour, array_keys($touchedStops));
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
            }

            return ['unplanned' => $unplanned, 'rejected' => $rejected];
        });
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

    /**
     * Retire les arrêts devenus vides et resserre les rangs. **Brouillons
     * seulement.**
     *
     * Un arrêt sans service actif n'est plus un arrêt : le camion n'y a plus
     * rien à faire. Le §115 demande de compacter les séquences ensuite, sans
     * quoi la tournée garderait des trous que l'ordre d'affichage révèle.
     *
     * @param  list<string>  $stopIds
     */
    private function pruneStops(Tour $tour, array $stopIds): void
    {
        $emptied = TourStop::whereIn('id', $stopIds)
            ->whereDoesntHave('services', fn ($services) => $services->where('is_active_assignment', true))
            ->get();

        if ($emptied->isEmpty()) {
            return;
        }

        foreach ($emptied as $stop) {
            $stop->delete();
        }

        $remaining = $tour->stops()->orderBy('sequence')->get();

        // Deux temps, comme a la planification : `unique(tour_id, sequence)`
        // refuse qu'un rang soit pris deux fois, meme le temps d'une boucle.
        $offset = (int) $remaining->max('sequence') + 1;

        foreach ($remaining as $index => $stop) {
            $stop->forceFill(['sequence' => $offset + $index])->save();
        }

        foreach ($remaining as $index => $stop) {
            $stop->forceFill(['sequence' => $index + 1])->save();
        }
    }
}
