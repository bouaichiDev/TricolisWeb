<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Addresses\Models\Address;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Providers\Models\Provider;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Database\Seeder;

/**
 * Une tournée confirmée, pour demain, attribuée à chacun des fournisseurs de
 * démonstration — de quoi affecter chauffeur et véhicule à la main comme dans
 * les parcours Playwright, et vérifier qu'Alpha ne voit pas celle de Beta.
 *
 * Hors du semis par défaut en test, comme la flotte de démonstration :
 *
 * ```bash
 * php artisan db:seed --class=DemoProviderPortalTourSeeder --env=testing
 * ```
 */
class DemoProviderPortalTourSeeder extends Seeder
{
    private const array TOURS = ['transports-alpha' => 'DEMO-ALPHA-1', 'transports-beta' => 'DEMO-BETA-1'];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $date = now()->addDay()->toDateString();

        foreach (self::TOURS as $code => $number) {
            $provider = Provider::where('code', $code)->first();
            $agency = $provider === null ? null : Agency::where('organization_id', $provider->organization_id)->first();

            if ($provider === null || $agency === null) {
                continue;
            }

            $tour = Tour::firstOrCreate(
                ['organization_id' => $provider->organization_id, 'tour_number' => $number],
                [
                    'agency_id' => $agency->id,
                    'provider_id' => $provider->id,
                    'tour_date' => $date,
                    'planned_start_at' => "{$date} 07:00:00",
                    'planned_end_at' => "{$date} 12:00:00",
                    'instructions' => 'Chargement au quai 3.',
                    'status' => TourStatus::CONFIRMED,
                ],
            );

            $this->stopFor($tour, "{$date} 08:00:00");
        }

        $this->executionTour($date);
        $this->call(DemoProviderPortalProofSeeder::class);
    }

    /**
     * Une seconde tournée d'Alpha, l'après-midi, déjà affectée à sa flotte de
     * démonstration : de quoi exécuter une tournée sans dépendre de l'affectation.
     * Remise à « confirmée » à chaque passage : le parcours se rejoue.
     */
    private function executionTour(string $date): void
    {
        $alpha = Provider::where('code', 'transports-alpha')->first();
        $agency = $alpha === null ? null : Agency::where('organization_id', $alpha->organization_id)->first();
        $driver = $alpha?->drivers()->where('code', 'ALPHA-D1')->first();
        $vehicle = $alpha?->vehicles()->where('code', 'ALPHA-V1')->first();

        if ($agency === null || $driver === null || $vehicle === null) {
            return;
        }

        $tour = Tour::updateOrCreate(
            ['organization_id' => $alpha->organization_id, 'tour_number' => 'DEMO-ALPHA-2'],
            [
                'agency_id' => $agency->id, 'provider_id' => $alpha->id, 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
                'tour_date' => $date, 'planned_start_at' => "{$date} 13:00:00", 'planned_end_at' => "{$date} 17:00:00",
                'status' => TourStatus::CONFIRMED, 'actual_start_at' => null, 'actual_end_at' => null,
            ],
        );

        $this->stopFor($tour, "{$date} 14:00:00");
        $tour->stops()->update(['status' => TourStopStatus::PENDING->value, 'actual_arrival_at' => null, 'actual_departure_at' => null]);
    }

    private function stopFor(Tour $tour, string $arrival): void
    {
        $address = Address::query()->first();

        if ($address !== null && $tour->wasRecentlyCreated) {
            TourStop::create([
                'tour_id' => $tour->id,
                'address_id' => $address->id,
                'sequence' => 1,
                'planned_arrival_at' => $arrival,
                'status' => TourStopStatus::PENDING,
            ]);
        }
    }
}
