<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Agencies\Models\Depot;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Stock\Actions\CreateStockMovementAction;
use App\Modules\Stock\Actions\CreateStockReservationAction;
use App\Modules\Stock\DTOs\CreateStockMovementData;
use App\Modules\Stock\DTOs\CreateStockReservationData;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Shared\Support\AuditContext;
use Illuminate\Database\Seeder;

/**
 * Démonstration du stock du portail (Phase 5) : IKEA et QOQA stockent chez le
 * transporteur, au même dépôt, **au même emplacement A** — le cas d'isolation
 * que la phase impose.
 *
 * Tout passe par les Actions internes (mouvements `inbound`, réservation) :
 * les balances sont calculées par elles, jamais écrites ici.
 *
 * **Hors du semis commun des tests** : une réservation sur une ligne de la
 * commande de démonstration bloquerait sa modification (clé étrangère), ce
 * que la suite de la Phase 2 exerce. Semé en développement, et à la demande
 * pour les parcours Playwright :
 *
 * ```bash
 * php artisan db:seed --class=DemoCustomerPortalStockSeeder --env=testing
 * ```
 */
class DemoCustomerPortalStockSeeder extends Seeder
{
    public function run(CreateStockMovementAction $move, CreateStockReservationAction $reserve): void
    {
        $ikea = Customer::where('code', 'ikea')->first();
        $qoqa = Customer::where('code', 'qoqa')->first();
        $depot = $ikea === null ? null : Depot::where('code', 'central')->whereHas('agency', fn ($agency) => $agency->where('organization_id', $ikea->organization_id))->first();

        if (! app()->environment('local', 'testing') || $ikea === null || $qoqa === null || $depot === null) {
            return;
        }

        if (StockItem::where('customer_id', $ikea->id)->where('article_code', 'BILLY-80')->exists()) {
            return;
        }

        $ikea->update(['stock_enabled' => true]);
        $qoqa->update(['stock_enabled' => true]);

        $a = StockLocation::firstOrCreate(['depot_id' => $depot->id, 'location_code' => 'A-01-01'], ['zone_code' => 'A', 'aisle' => '01', 'rack' => '01', 'level' => '1', 'status' => 'active']);
        $b = StockLocation::firstOrCreate(['depot_id' => $depot->id, 'location_code' => 'B-02-03'], ['zone_code' => 'B', 'aisle' => '02', 'rack' => '03', 'level' => '2', 'status' => 'active']);

        $catalogItem = CustomerCatalogItem::where('article_code', 'BILLY-80')->whereHas('catalog', fn ($catalog) => $catalog->where('customer_id', $ikea->id))->first();
        $billy = StockItem::create(['customer_id' => $ikea->id, 'catalog_item_id' => $catalogItem?->id, 'article_code' => 'BILLY-80', 'description' => 'Bibliothèque Billy 80 cm', 'status' => 'active']);
        $table = StockItem::create(['customer_id' => $qoqa->id, 'article_code' => 'QOQA-TABLE', 'description' => 'Table QOQA', 'status' => 'active']);

        $context = new AuditContext($ikea->organization_id);
        $now = now()->subDays(2)->toDateTimeString();

        $move->execute(new CreateStockMovementData($billy->id, 'inbound', '50', destinationLocationId: $a->id), $context, $now);
        $move->execute(new CreateStockMovementData($billy->id, 'inbound', '70', destinationLocationId: $b->id), $context, $now);
        $move->execute(new CreateStockMovementData($table->id, 'inbound', '30', destinationLocationId: $a->id), $context, $now);

        $line = Order::where('customer_id', $ikea->id)->oldest()->first()?->lines()->where('catalog_item_id', $catalogItem?->id)->first();

        if ($line !== null) {
            $reserve->execute(new CreateStockReservationData($billy->id, $a->id, $line->id, '10', 'active'), $context, now()->toDateTimeString());
        }
    }
}
