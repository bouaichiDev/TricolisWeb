<?php

declare(strict_types=1);

namespace App\Modules\Planning\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Planning\Jobs\RecalculateTourRouteJob;
use App\Modules\Planning\Services\DepotAddress;
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
        private EnsureLoadingService $loading,
        private DepotAddress $depot,
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
            $eligible = [];

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

                $eligible[] = $service;
            }

            // Les chargements manquants **avant** d'ecrire : une commande dont
            // le chargement ne peut pas etre cree est refusee entiere, plutot
            // que de laisser sa livraison seule dans la tournee.
            [$eligible, $rejected] = $this->withLoadings($tour, $eligible, $rejected);

            foreach ($eligible as $service) {
                $this->assign($tour, $service);
                $planned[] = $service->id;
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

    /**
     * Ajoute les chargements manquants, ou refuse les commandes qui n'en
     * peuvent pas avoir.
     *
     * L'option est par organisation : celle qui ne l'active pas garde le
     * comportement d'avant, où une livraison se planifie seule.
     *
     * Le refus porte sur **toute la commande**, pas sur le seul chargement
     * absent : planifier la livraison en annonçant que le chargement a échoué
     * laisserait une tournée qu'on croit complète et qui ne l'est pas.
     *
     * @param  list<OrderService>  $eligible
     * @param  list<array{orderServiceId: string, reason: string}>  $rejected
     * @return array{0: list<OrderService>, 1: list<array{orderServiceId: string, reason: string}>}
     */
    private function withLoadings(Tour $tour, array $eligible, array $rejected): array
    {
        if ($eligible === []) {
            return [$eligible, $rejected];
        }

        $organization = Organization::find($tour->organization_id);

        if ($organization === null || ! $this->loading->isEnabled($organization)) {
            return [$eligible, $rejected];
        }

        $orders = Order::whereIn('id', array_unique(array_map(
            static fn (OrderService $service): string => $service->order_id,
            $eligible,
        )))->with('packages')->get();

        // Une seule fois pour toute la fournee : le motif ne depend que de la
        // tournee et de l'organisation, pas de la commande.
        $refusal = $this->loading->refusalFor($tour, $organization);
        $refused = [];

        foreach ($orders as $order) {
            if ($this->loading->alreadyCarried($order, $organization)) {
                continue;
            }

            if ($refusal !== null) {
                $refused[] = $order->id;

                continue;
            }

            $eligible[] = $this->loading->create($tour, $order, $organization);
        }

        if ($refused === []) {
            return [$eligible, $rejected];
        }

        foreach ($eligible as $service) {
            if (in_array($service->order_id, $refused, true)) {
                $rejected[] = ['orderServiceId' => $service->id, 'reason' => (string) $refusal];
            }
        }

        return [
            array_values(array_filter(
                $eligible,
                static fn (OrderService $service): bool => ! in_array($service->order_id, $refused, true),
            )),
            $rejected,
        ];
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
            // Pendant une composition, l'affectation attend d'etre confirmee :
            // les colonnes ne doivent pas montrer un plan a moitie fait.
            'confirmed_at' => $tour->locked_by === null ? now() : null,
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
        $depotAddressId = $this->depot->for($tour);

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
}
