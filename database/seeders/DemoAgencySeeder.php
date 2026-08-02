<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Agences et dépôts de démonstration de l'organisation de développement.
 */
class DemoAgencySeeder extends Seeder
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

        $agency = Agency::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'main'],
            [
                'name' => 'Agence principale',
                'short_name' => 'Main',
                'email' => 'main@tricolis.dev',
                'status' => 'active',
            ]
        );

        Depot::firstOrCreate(
            ['agency_id' => $agency->id, 'code' => 'central'],
            [
                'name' => 'Dépôt central',
                'status' => 'active',
            ]
        );
    }
}
