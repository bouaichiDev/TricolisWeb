<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Agencies\Models\Depot;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Stock\Actions\CreateStockMovementAction;
use App\Modules\Stock\Actions\CreateStockReservationAction;
use App\Modules\Stock\DTOs\CreateStockMovementData;
use App\Modules\Stock\DTOs\CreateStockReservationData;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Models\StockReservation;
use App\Shared\Support\AuditContext;

/**
 * Stock de test du portail (Phase 5), écrit **par les Actions internes** :
 * les balances sont celles que le module calcule, pas des lignes posées à la
 * main. IKEA et QOQA partagent le dépôt central du transporteur.
 */
final class CustomerPortalStock
{
    /** Ouvre le module stock aux deux clients de démonstration. */
    public static function enable(): void
    {
        CustomerPortal::customer('ikea')->update(['stock_enabled' => true]);
        CustomerPortal::customer('qoqa')->update(['stock_enabled' => true]);
    }

    public static function location(string $code): StockLocation
    {
        $organizationId = CustomerPortal::customer('ikea')->organization_id;
        $depot = Depot::where('code', 'central')->whereHas('agency', static fn ($agency) => $agency->where('organization_id', $organizationId))->firstOrFail();

        return StockLocation::factory()->forDepot($depot)->create(['location_code' => $code, 'zone_code' => substr($code, 0, 1), 'status' => 'active']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function item(string $customerCode, string $articleCode, array $attributes = []): StockItem
    {
        return StockItem::factory()->forCustomer(CustomerPortal::customer($customerCode))->create(['article_code' => $articleCode, 'status' => 'active', ...$attributes]);
    }

    public static function receive(StockItem $item, StockLocation $location, string $quantity, string $type = 'inbound'): StockMovement
    {
        return app(CreateStockMovementAction::class)->execute(
            new CreateStockMovementData($item->id, $type, $quantity, destinationLocationId: $location->id),
            new AuditContext($location->depot->agency->organization_id),
            now()->toDateTimeString(),
        );
    }

    public static function reserve(StockItem $item, StockLocation $location, OrderLine $line, string $quantity): StockReservation
    {
        return app(CreateStockReservationAction::class)->execute(
            new CreateStockReservationData($item->id, $location->id, $line->id, $quantity, 'active'),
            new AuditContext($location->depot->agency->organization_id),
            now()->toDateTimeString(),
        );
    }

    public static function line(string $customerCode): OrderLine
    {
        return CustomerPortalOrders::order($customerCode)->lines()->firstOr(static fn () => OrderLine::factory()->create(['order_id' => CustomerPortalOrders::order($customerCode)->id]));
    }
}
