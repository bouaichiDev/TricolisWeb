<?php

declare(strict_types=1);

namespace App\Modules\Planning\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Planning\Jobs\RecalculateTourRouteJob;
use App\Modules\Planning\Services\PlanningEligibility;
use App\Modules\Planning\Services\StopGrouping;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStopService;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Fait entrer des services dans une tournée, d'un seul geste.
 *
 * **Un appel, pas un par service.** Glisser une commande de huit services ne
 * doit pas produire huit requêtes : la moitié pourrait réussir, l'autre échouer,
 * et la tournée resterait à mi-chemin sans que personne sache où.
 *
 * **Les éligibles passent, les autres sont nommés.** C'est la règle retenue le
 * 26 août 2026 face au §42 : un service déjà livré ne doit pas empêcher de
 * planifier le reste de sa commande. Chaque refus rend son motif, en code, que
 * l'interface traduit.
 *
 * **Une seule affectation active par service.** Les lignes sont verrouillées le
 * temps de la transaction : deux planificateurs visant le même service au même
 * instant ne peuvent pas l'affecter deux fois.
 *
 * **Les chargements au dépôt ouvrent la tournée.** Un arrêt à l'adresse du
 * dépôt qui ne porte que des chargements passe en tête, quel que soit l'ordre
 * dans lequel on a glissé les commandes : on charge avant de partir.
 */
final readonly class PlanOrderServices
{
    public function __construct(
        private PlanningEligibility $eligibility,
        private StopGrouping $grouping,
        private TourTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @param  list<string>  $orderServiceIds
     * @return array{planned: list<string>, rejected: list<array{orderServiceId: string, reason: string}>}
     */
    public function execute(Tour $tour, array $orderServiceIds, AuditContext $context): array
    {
        if ($orderServiceIds === []) {
            return ['planned' => [], 'rejected' => []];
        }

        return DB::transaction(function () use ($tour, $orderServiceIds, $context): array {
            // Verrouillees ensemble : entre le controle d'eligibilite et
            // l'ecriture, un autre planificateur pourrait prendre le meme
            // service.
            $services = OrderService::whereIn('id', $orderServiceIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $planned = [];
            $rejected = [];

            foreach ($orderServiceIds as $id) {
                $service = $services->get($id);

                if ($service === null) {
                    $rejected[] = ['orderServiceId' => $id, 'reason' => 'not_found'];

                    continue;
                }

                $refusal = $this->eligibility->refusalFor($service);

                if ($refusal !== null) {
                    $rejected[] = ['orderServiceId' => $id, 'reason' => $refusal];

                    continue;
                }

                $this->assign($tour, $service);
                $planned[] = $id;
            }

            if ($planned !== []) {
                $this->promoteDepotLoading($tour);
                $this->totals->recalculate($tour);

                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'tour.services_planned',
                    $tour,
                    null,
                    ['planned' => $planned, 'rejected' => array_column($rejected, 'reason')],
                    null,
                    $context->ipAddress,
                );

                // La composition a change : l'itineraire connu decrit un ordre
                // qui n'existe plus. Le calcul part en file, apres la
                // transaction, pour ne pas faire attendre le depot.
                RecalculateTourRouteJob::dispatch($tour->id)->afterCommit();
            }

            return ['planned' => $planned, 'rejected' => $rejected];
        });
    }

    /** Pose le service sur son arrêt, et le marque planifié. */
    private function assign(Tour $tour, OrderService $service): void
    {
        $stop = $this->grouping->stopFor($tour, $service);

        $position = (int) TourStopService::where('tour_stop_id', $stop->id)
            ->max('sequence_within_stop') + 1;

        TourStopService::create([
            'tour_stop_id' => $stop->id,
            'order_service_id' => $service->id,
            'sequence_within_stop' => $position,
            'is_active_assignment' => true,
            'status' => 'planned',
        ]);

        $service->forceFill(['status' => OrderServiceStatus::PLANNED->value])->save();
    }

    /**
     * Remonte en tête l'arrêt de chargement au dépôt.
     *
     * L'arrêt existe même s'il partage l'adresse du départ : c'est lui qui
     * porte les services de chargement, et le supprimer les laisserait sans
     * attache. Les autres arrêts se décalent, leur ordre relatif conservé.
     */
    private function promoteDepotLoading(Tour $tour): void
    {
        $depotAddressId = $this->depotAddressId($tour);

        if ($depotAddressId === null) {
            return;
        }

        $stops = $tour->stops()->orderBy('sequence')->get();
        $first = $stops->firstWhere('address_id', $depotAddressId);

        if ($first === null || $first->sequence === 1) {
            return;
        }

        $ordered = $stops->reject(fn ($stop): bool => $stop->id === $first->id)->values()->prepend($first);

        // Deux temps : `unique(tour_id, sequence)` refuse qu'un rang soit pris
        // deux fois, meme une fraction de transaction. Les rangs partent donc
        // au-dela du dernier avant de revenir a leur place.
        $offset = (int) $stops->max('sequence') + 1;

        foreach ($ordered as $index => $stop) {
            $stop->forceFill(['sequence' => $offset + $index])->save();
        }

        foreach ($ordered as $index => $stop) {
            $stop->forceFill(['sequence' => $index + 1])->save();
        }
    }

    private function depotAddressId(Tour $tour): ?string
    {
        if ($tour->depot_id === null) {
            return null;
        }

        return DB::table('entity_addresses')
            ->where('entity_type', 'depot')
            ->where('entity_id', $tour->depot_id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('address_id');
    }
}
