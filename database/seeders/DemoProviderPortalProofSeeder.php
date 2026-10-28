<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Providers\Models\Provider;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use Database\Seeders\Support\ProviderDemoOrder;
use Illuminate\Database\Seeder;

/**
 * Une tournée d'Alpha déjà en cours, son arrêt atteint, un service confirmé
 * dessus : de quoi saisir une preuve de livraison dans les parcours Playwright.
 *
 * Remise à zéro à chaque passage (statuts, preuves de l'arrêt de démo) pour
 * que le parcours se rejoue sans base neuve. Local et test seulement ; le
 * service vient de `ProviderDemoOrder`, une commande passée par le service
 * métier, jamais une commande suivie par les démonstrations du portail client.
 * Appelé par `DemoProviderPortalTourSeeder`.
 */
class DemoProviderPortalProofSeeder extends Seeder
{
    public const string TOUR = 'DEMO-ALPHA-3';

    public function run(ProviderDemoOrder $order): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $alpha = Provider::where('code', 'transports-alpha')->first();
        $agency = $alpha === null ? null : Agency::where('organization_id', $alpha->organization_id)->first();
        $address = Address::query()->first();

        if ($alpha === null || $agency === null || $address === null) {
            return;
        }

        $date = now()->addDay()->toDateString();
        $tour = Tour::updateOrCreate(
            ['organization_id' => $alpha->organization_id, 'tour_number' => self::TOUR],
            [
                'agency_id' => $agency->id, 'provider_id' => $alpha->id, 'tour_date' => $date,
                'planned_start_at' => "{$date} 18:00:00", 'planned_end_at' => "{$date} 20:00:00",
                'status' => TourStatus::IN_PROGRESS, 'actual_start_at' => "{$date} 18:05:00", 'actual_end_at' => null, 'locked_by' => null,
            ],
        );
        $stop = TourStop::updateOrCreate(
            ['tour_id' => $tour->id, 'sequence' => 1],
            ['address_id' => $address->id, 'planned_arrival_at' => "{$date} 18:30:00", 'status' => TourStopStatus::ARRIVED, 'actual_arrival_at' => "{$date} 18:32:00", 'actual_departure_at' => null],
        );

        ProofOfDelivery::where('tour_stop_id', $stop->id)->delete();

        if (TourStopService::where('tour_stop_id', $stop->id)->exists()) {
            return;
        }

        $service = $order->service($alpha->organization);

        if ($service !== null) {
            TourStopService::create([
                'tour_stop_id' => $stop->id, 'order_service_id' => $service->id, 'sequence_within_stop' => 1,
                'is_active_assignment' => true, 'confirmed_at' => now(), 'status' => 'planned',
            ]);
        }
    }
}
