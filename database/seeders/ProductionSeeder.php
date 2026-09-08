<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Referential data required by a production installation.
 *
 * Development accounts, demo organizations and sample business data are
 * intentionally excluded.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            StatusSeeder::class,
            StatusTransitionSeeder::class,
            PricingVariableSeeder::class,
            RoleSeeder::class,
            PasswordResetTemplateSeeder::class,
        ]);
    }
}
