<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use Illuminate\Database\Seeder;

/**
 * Fournisseur, chauffeur, types de véhicule et véhicule de démonstration.
 *
 * Réservé aux environnements local et de test. Aucune donnée de production :
 * les valeurs de `provider_type` et de `status` sont des exemples, le diagramme
 * n'en énumère aucune.
 */
class DemoFleetSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $organization = Organization::where('code', DevelopmentOrganizationSeeder::ORGANIZATION_CODE)->first();

        if ($organization === null) {
            return;
        }

        $provider = Provider::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'demo-provider'],
            [
                'name' => 'Transporteur partenaire démo',
                'provider_type' => 'subcontractor',
                'status' => 'active',
            ]
        );

        Driver::firstOrCreate(
            ['provider_id' => $provider->id, 'code' => 'demo-driver'],
            [
                'first_name' => 'Karim',
                'last_name' => 'Bensaïd',
                'phone' => '+212661000000',
                'email' => 'karim.demo@tricolis.dev',
                'status' => 'active',
            ]
        );

        $vanType = VehicleType::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'van'],
            ['name' => 'Fourgon', 'status' => 'active']
        );

        VehicleType::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'truck-12t'],
            ['name' => 'Porteur 12T', 'status' => 'active']
        );

        Vehicle::firstOrCreate(
            ['provider_id' => $provider->id, 'code' => 'demo-van'],
            [
                'vehicle_type_id' => $vanType->id,
                'registration_number' => 'DEMO-0001',
                'payload_capacity' => 1200,
                'volume_capacity' => 12.5,
                'pallet_capacity' => 4,
                'status' => 'active',
            ]
        );
    }
}
