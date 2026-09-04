<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Actions\GenerateTourNumber;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use Carbon\CarbonImmutable;

/**
 * La tournée qui a réellement effectué les livraisons du jour.
 *
 * Sans elle, les prestations seraient facturables au client mais réglables à
 * personne : le §17 ne paie que le fournisseur de **l'affectation active**, et
 * cette affectation n'existe que sur une tournée.
 *
 * Le chargement est regroupé sur un arrêt au dépôt, les livraisons ont chacune
 * le leur — c'est la forme qu'une tournée prend réellement, et celle que les
 * écrans de planification savent relire.
 */
final class DeliveredTourBuilder
{
    private int $sequence = 1;

    public function __construct(
        private readonly string $organizationId,
        private readonly string $agencyId,
        private readonly string $depotId,
        private readonly string $depotAddressId,
    ) {}

    /** Ouvre la tournée du jour et son arrêt de chargement. */
    public function open(CarbonImmutable $date, ?string $providerId): Tour
    {
        $this->sequence = 1;

        $tour = Tour::create([
            'organization_id' => $this->organizationId,
            'agency_id' => $this->agencyId,
            'depot_id' => $this->depotId,
            'provider_id' => $providerId,
            'tour_number' => app(GenerateTourNumber::class)->execute($this->organizationId),
            'tour_date' => $date->toDateString(),
            'planned_start_at' => $date->setTime(7, 30),
            'planned_end_at' => $date->setTime(17, 0),
            'status' => 'completed',
        ]);

        // L'arret de depot porte tous les chargements du jour : on ne repasse
        // pas au depot entre deux clients.
        TourStop::create([
            'tour_id' => $tour->id,
            'address_id' => $this->depotAddressId,
            'sequence' => $this->sequence++,
            'status' => 'completed',
        ]);

        return $tour;
    }

    /** Rattache le chargement au dépôt et la livraison à son propre arrêt. */
    public function attach(Tour $tour, OrderService $loading, OrderService $delivery): void
    {
        $depotStop = $tour->stops()->orderBy('sequence')->first();

        if ($depotStop !== null) {
            $this->assign($depotStop, $loading);
        }

        $stop = TourStop::create([
            'tour_id' => $tour->id,
            'address_id' => $delivery->address_id,
            'sequence' => $this->sequence++,
            'status' => 'completed',
        ]);

        $this->assign($stop, $delivery);
    }

    /** Fige les totaux de la tournée sur ce qu'elle a réellement transporté. */
    public function close(Tour $tour): void
    {
        $services = OrderService::whereIn(
            'id',
            TourStopService::whereIn('tour_stop_id', $tour->stops()->pluck('id'))->pluck('order_service_id'),
        )->get();

        $tour->forceFill([
            'total_weight' => (float) $services->sum('weight'),
            'total_volume' => (float) $services->sum('volume'),
            'total_packages' => (int) $services->sum('package_count'),
            'total_customers' => $services->pluck('order_id')->unique()->count(),
        ])->save();
    }

    private function assign(TourStop $stop, OrderService $service): void
    {
        TourStopService::create([
            'tour_stop_id' => $stop->id,
            'order_service_id' => $service->id,
            'sequence_within_stop' => TourStopService::where('tour_stop_id', $stop->id)->count() + 1,
            'is_active_assignment' => true,
            // Confirmee : elle n'est pas le brouillon d'une composition en
            // cours, elle raconte ce qui a eu lieu.
            'confirmed_at' => now(),
            'status' => 'completed',
        ]);
    }
}
