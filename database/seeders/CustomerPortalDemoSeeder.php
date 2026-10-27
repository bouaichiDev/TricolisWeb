<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Démonstration du portail client : IKEA et QOQA, leurs comptes, leur carnet
 * d'adresses, le catalogue d'IKEA, une première commande chacun, et
 * le statut par défaut des réclamations.
 *
 * **Hors du semis par défaut en test.** La suite existante compte les clients,
 * les adresses et les commandes semés, et crée ses propres services — dont un
 * `DELIVERY` que ce semis poserait avant elle. Les tests du portail et les
 * parcours Playwright le demandent explicitement :
 *
 * ```bash
 * php artisan db:seed --class=CustomerPortalDemoSeeder --env=testing
 * ```
 */
class CustomerPortalDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Chaque démonstration appelée se garde déjà ; celle-ci aussi, pour
        // qu'un `--force` en production ne compte sur aucune d'elles.
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $this->call([
            DemoCustomerPortalSeeder::class,
            DemoCustomerPortalOrderSeeder::class,
            DemoCustomerPortalAfterDeliverySeeder::class,
        ]);
    }
}
