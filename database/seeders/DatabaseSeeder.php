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
            // Referentiel commun a la plateforme : seme a partir des
            // enumerations existantes, avant toute donnee de demonstration.
            StatusSeeder::class,
            StatusTransitionSeeder::class,
            DevelopmentOrganizationSeeder::class,
            RoleSeeder::class,
            // Après RoleSeeder : le compte s'accroche au rôle plateforme que
            // celui-ci vient de créer.
            PlatformAdminSeeder::class,
            DemoAgencySeeder::class,
            DemoCustomerSeeder::class,
            DemoFleetSeeder::class,
            // Les deux services GPS : sans eux, geocodage et itineraires
            // s'executent sans rien changer, ce qui ressemble a une panne.
            GpsApiConfigurationSeeder::class,
            // Un mois de commandes suisses, pretes a planifier : depot d'abord,
            // les commandes s'y accrochent.
            SwissDepotSeeder::class,
            SwissOrderSeeder::class,
        ]);
    }
}
