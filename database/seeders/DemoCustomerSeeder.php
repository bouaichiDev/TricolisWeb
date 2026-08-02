<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Clients de démonstration, réservés aux environnements local et de test.
 */
class DemoCustomerSeeder extends Seeder
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

        Customer::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'demo-client'],
            [
                'name' => 'Client démo',
                'legal_name' => 'Client Démo SA',
                'email' => 'demo@client.dev',
                'status' => CustomerStatus::ACTIVE,
            ]
        );
    }
}
