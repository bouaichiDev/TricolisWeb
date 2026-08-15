<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            DevelopmentOrganizationSeeder::class,
            RoleSeeder::class,
            DemoAgencySeeder::class,
            DemoCustomerSeeder::class,
            DemoFleetSeeder::class,
        ]);
    }
}
