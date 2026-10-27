<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * Catalogues de démonstration des **autres** clients (QOQA, GETAZ), pour voir
 * et prouver l'isolation du catalogue : IKEA ne lit ni ne modifie jamais ces
 * articles. Le catalogue IKEA vient de `DemoCustomerPortalOrderSeeder`.
 *
 * Environnements `local` et `testing` seulement, rejouable sans doublon.
 * Hors du semis des tests Pest, comme les autres démonstrations du portail :
 *
 *     php artisan db:seed --class=DemoCustomerPortalCatalogSeeder --env=testing
 */
class DemoCustomerPortalCatalogSeeder extends Seeder
{
    /** @var array<string, list<array{string, string, float}>> */
    private const array ARTICLES = [
        'qoqa' => [['QOQA-TABLE-80', 'Table QOQA 80 cm', 18.0], ['QOQA-CHAISE', 'Chaise QOQA', 4.5]],
        'getaz' => [['GETAZ-PORTE', 'Porte intérieure Getaz', 25.0]],
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach (self::ARTICLES as $code => $articles) {
            $customer = Customer::where('code', $code)->first();

            if ($customer === null) {
                continue;
            }

            $customer->update(['catalog_enabled' => true]);
            $catalog = CustomerCatalog::firstOrCreate(
                ['customer_id' => $customer->id, 'code' => 'main'],
                ['name' => 'Catalogue '.$customer->name, 'status' => 'active'],
            );

            foreach ($articles as [$articleCode, $name, $weight]) {
                $catalog->items()->firstOrCreate(['article_code' => $articleCode], ['name' => $name, 'weight' => $weight, 'status' => 'active']);
            }
        }
    }
}
