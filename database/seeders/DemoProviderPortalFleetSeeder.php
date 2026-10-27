<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Providers\Models\Provider;
use App\Modules\Types\Models\TypeItem;
use Illuminate\Database\Seeder;

/**
 * Un chauffeur et un véhicule pour chacun des fournisseurs de démonstration.
 *
 * **Hors** de `DemoProviderPortalSeeder` : les tests du portail comptent les
 * chauffeurs et véhicules qu'ils créent, et ceux-ci fausseraient le compte.
 * Semé en développement et pour les parcours Playwright :
 *
 * ```bash
 * php artisan db:seed --class=DemoProviderPortalFleetSeeder --env=testing
 * ```
 *
 * Réservé aux environnements local et de test. Rejouable sans doublon.
 */
class DemoProviderPortalFleetSeeder extends Seeder
{
    /** @var array<string, array{driver: array{string, string}, vehicle: array{string, string}}> */
    private const array FLEET = [
        'transports-alpha' => ['driver' => ['ALPHA-D1', 'Adam Alpha'], 'vehicle' => ['ALPHA-V1', 'GE-ALPHA-1']],
        'transports-beta' => ['driver' => ['BETA-D1', 'Basile Beta'], 'vehicle' => ['BETA-V1', 'GE-BETA-1']],
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach (self::FLEET as $code => $fleet) {
            $provider = Provider::where('code', $code)->first();
            $type = $provider === null ? null : TypeItem::where('organization_id', $provider->organization_id)
                ->whereHas('type', static fn ($query) => $query->where('code', 'vehicle'))
                ->first();

            if ($provider === null || $type === null) {
                continue;
            }

            Driver::firstOrCreate(
                ['organization_id' => $provider->organization_id, 'code' => $fleet['driver'][0]],
                ['provider_id' => $provider->id, 'name' => $fleet['driver'][1], 'status' => 'active'],
            );

            Vehicle::firstOrCreate(
                ['organization_id' => $provider->organization_id, 'code' => $fleet['vehicle'][0]],
                [
                    'provider_id' => $provider->id,
                    'vehicle_type_id' => $type->id,
                    'registration_number' => $fleet['vehicle'][1],
                    'payload_capacity' => 1500,
                    'volume_capacity' => 14,
                    'pallet_capacity' => 6,
                    'status' => 'active',
                ],
            );
        }
    }
}
