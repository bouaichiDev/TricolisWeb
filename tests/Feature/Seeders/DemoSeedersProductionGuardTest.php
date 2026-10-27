<?php

use Database\Seeders\CustomerPortalDemoSeeder;
use Database\Seeders\DeliveredOrderSeeder;
use Database\Seeders\DemoAgencySeeder;
use Database\Seeders\DemoCustomerPortalAfterDeliverySeeder;
use Database\Seeders\DemoCustomerPortalCatalogSeeder;
use Database\Seeders\DemoCustomerPortalIntegrationsSeeder;
use Database\Seeders\DemoCustomerPortalOrderSeeder;
use Database\Seeders\DemoCustomerPortalSeeder;
use Database\Seeders\DemoCustomerPortalStockSeeder;
use Database\Seeders\DemoCustomerSeeder;
use Database\Seeders\DemoFleetSeeder;
use Database\Seeders\DemoProviderPortalFleetSeeder;
use Database\Seeders\DemoProviderPortalSeeder;
use Database\Seeders\DemoProviderPortalTourSeeder;
use Database\Seeders\DevelopmentOrganizationSeeder;
use Database\Seeders\GpsApiConfigurationSeeder;
use Database\Seeders\PlatformAdminSeeder;
use Database\Seeders\SwissDepotSeeder;
use Database\Seeders\SwissOrderSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Aucune donnée de démonstration ne peut atteindre la production, même par un
 * `php artisan db:seed --class=… --force` lancé par erreur : chaque seeder de
 * démonstration, exécuté en environnement `production`, n'écrit rien.
 */
const DEMO_TABLES = [
    'organizations', 'users', 'customers', 'customer_users', 'orders', 'agencies', 'depots', 'vehicles',
    'customer_catalogs', 'customer_catalog_items', 'stock_items', 'stock_movements', 'claims',
    'customer_import_configurations', 'customer_export_configurations', 'organization_api_configurations',
    'providers', 'provider_users', 'provider_user_roles', 'drivers', 'tours', 'tour_stops',
];

beforeEach(function (): void {
    // Les prérequis des démonstrations existent (clients IKEA, QOQA, dépôt…) :
    // seule la garde d'environnement peut alors empêcher l'écriture.
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->seed(DemoProviderPortalSeeder::class);
    $this->counts = static fn (): array => collect(DEMO_TABLES)->mapWithKeys(static fn (string $table): array => [$table => DB::table($table)->count()])->all();
});

afterEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'testing');
});

it('n’écrit rien en production', function (string $seeder): void {
    $before = ($this->counts)();
    app()->detectEnvironment(static fn (): string => 'production');

    $this->artisan('db:seed', ['--class' => $seeder, '--force' => true])->assertSuccessful();

    expect(($this->counts)())->toBe($before);
})->with([
    CustomerPortalDemoSeeder::class, DemoCustomerPortalSeeder::class, DemoCustomerPortalOrderSeeder::class,
    DemoCustomerPortalAfterDeliverySeeder::class, DemoCustomerPortalStockSeeder::class, DemoCustomerPortalIntegrationsSeeder::class,
    DemoCustomerPortalCatalogSeeder::class, DemoAgencySeeder::class, DemoCustomerSeeder::class, DemoFleetSeeder::class,
    DevelopmentOrganizationSeeder::class, DeliveredOrderSeeder::class, SwissDepotSeeder::class, SwissOrderSeeder::class,
    GpsApiConfigurationSeeder::class, PlatformAdminSeeder::class, DemoProviderPortalSeeder::class,
    DemoProviderPortalFleetSeeder::class, DemoProviderPortalTourSeeder::class,
]);
