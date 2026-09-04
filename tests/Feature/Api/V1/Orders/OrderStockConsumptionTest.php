<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Service;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Shared\Database\MorphMap;

/**
 * Confirmer une commande sort sa marchandise du stock.
 *
 * C'est le seul effet de bord d'un changement de statut, et il vit dans la même
 * transaction : une commande confirmée dont le stock n'aurait pas bougé — ou
 * l'inverse — laisserait un dépôt qui ment.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::where('organization_id', $this->organization->id)->firstOrFail();
    $this->agency = Agency::where('organization_id', $this->organization->id)->firstOrFail();

    $this->address = Address::factory()->create();
    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $this->address->id,
        'entity_type' => MorphMap::ORGANIZATION,
        'entity_id' => $this->organization->id,
    ]);

    $this->service = Service::factory()->forOrganization($this->organization)->create();
    $this->depot = Depot::create([
        'agency_id' => $this->agency->id, 'code' => 'DEP-1', 'name' => 'Dépôt 1', 'status' => 'active',
    ]);

    $catalog = CustomerCatalog::factory()->forCustomer($this->customer)->create();
    $this->catalogItem = CustomerCatalogItem::factory()->forCatalog($catalog)->create(['article_code' => 'ART-1']);

    $this->stockItem = StockItem::create([
        'customer_id' => $this->customer->id,
        'catalog_item_id' => $this->catalogItem->id,
        'article_code' => 'ART-1',
        'status' => 'active',
    ]);

    $this->location = fn (string $code): StockLocation => StockLocation::create([
        'depot_id' => $this->depot->id, 'location_code' => $code, 'status' => 'active',
    ]);

    $this->stockAt = function (StockLocation $location, string $quantity): StockBalance {
        return StockBalance::create([
            'stock_item_id' => $this->stockItem->id,
            'stock_location_id' => $location->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'available_quantity' => $quantity,
            'updated_at' => now(),
        ]);
    };

    $this->createOrder = function (int $quantity = 4, bool $catalogued = true): string {
        $line = ['name' => 'Canapé', 'quantity' => $quantity, 'weight' => 10];
        if ($catalogued) {
            $line['catalogItemId'] = $this->catalogItem->id;
        }

        return $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', [
                'customerId' => $this->customer->id,
                'agencyId' => $this->agency->id,
                'orderDate' => now()->toISOString(),
                'lines' => [$line],
                'services' => [[
                    'serviceId' => $this->service->id, 'addressId' => $this->address->id,
                    'serviceNumber' => 'SRV-1', 'sequence' => 1, 'requestedDate' => now()->toDateString(),
                    'quantity' => 1, 'unit' => 'delivery', 'requiredTimeMinutes' => 30,
                    'remainingTimeMinutes' => 30, 'weight' => 0, 'volume' => 0, 'packageCount' => 0,
                    'customerUnitPrice' => 0, 'customerTotalPrice' => 0,
                    'providerUnitCost' => 0, 'providerTotalCost' => 0, 'status' => 'draft',
                ]],
            ])->assertCreated()->json('data.id');
    };

    $this->confirm = fn (string $orderId, array $payload = []) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'confirmed'] + $payload);
});

it('sort la quantite commandee du seul emplacement qui la porte', function (): void {
    $location = ($this->location)('A-1');
    $balance = ($this->stockAt)($location, '10');
    $orderId = ($this->createOrder)(4);

    ($this->confirm)($orderId)->assertOk();

    expect((string) $balance->fresh()->quantity)->toBe('6.000')
        ->and((string) $balance->fresh()->available_quantity)->toBe('6.000');

    $this->assertDatabaseHas('stock_movements', [
        'stock_item_id' => $this->stockItem->id,
        'source_location_id' => $location->id,
        'destination_location_id' => null,
        'movement_type' => 'order_confirmation',
        'source_entity_type' => MorphMap::ORDER_LINE,
    ]);
});

/**
 * Deux emplacements portent l'article : lequel vider ne se devine pas. La
 * commande reste en brouillon plutot que d'etre confirmee au hasard.
 */
it('refuse de confirmer quand l article dort dans plusieurs emplacements', function (): void {
    ($this->stockAt)(($this->location)('A-1'), '10');
    ($this->stockAt)(($this->location)('B-2'), '10');
    $orderId = ($this->createOrder)(4);

    ($this->confirm)($orderId)->assertStatus(422)->assertJsonValidationErrors('stockLocations');

    $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'draft']);
    $this->assertDatabaseCount('stock_movements', 0);
});

it('accepte l emplacement designe par l appelant', function (): void {
    $chosen = ($this->location)('A-1');
    $other = ($this->location)('B-2');
    $balance = ($this->stockAt)($chosen, '10');
    $untouched = ($this->stockAt)($other, '10');
    $orderId = ($this->createOrder)(4);

    $lineId = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$orderId}")->json('data.lines.0.id');

    ($this->confirm)($orderId, [
        'stockLocations' => [['orderLineId' => $lineId, 'stockLocationId' => $chosen->id]],
    ])->assertOk();

    expect((string) $balance->fresh()->quantity)->toBe('6.000')
        ->and((string) $untouched->fresh()->quantity)->toBe('10.000');
});

/** Confirmer deux fois ne preleve pas deux fois : le mouvement porte la ligne. */
it('ne preleve pas deux fois quand la commande repasse par confirmee', function (): void {
    $balance = ($this->stockAt)(($this->location)('A-1'), '10');
    $orderId = ($this->createOrder)(4);

    ($this->confirm)($orderId)->assertOk();
    expect((string) $balance->fresh()->quantity)->toBe('6.000');

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'draft'])->assertOk();
    ($this->confirm)($orderId)->assertOk();

    expect((string) $balance->fresh()->quantity)->toBe('6.000');
    $this->assertDatabaseCount('stock_movements', 1);
});

it('refuse de confirmer quand le stock ne couvre pas la quantite', function (): void {
    ($this->stockAt)(($this->location)('A-1'), '2');
    $orderId = ($this->createOrder)(4);

    ($this->confirm)($orderId)->assertStatus(422)->assertJsonValidationErrors('stockLocations');
    $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'draft']);
});

/** Une ligne saisie a la main n'a pas d'article : elle ne touche pas au stock. */
it('confirme sans toucher au stock une ligne hors catalogue', function (): void {
    ($this->stockAt)(($this->location)('A-1'), '10');
    $orderId = ($this->createOrder)(4, catalogued: false);

    ($this->confirm)($orderId)->assertOk();

    $this->assertDatabaseCount('stock_movements', 0);
});

it('annonce a l avance ce que la confirmation sortirait', function (): void {
    $location = ($this->location)('A-1');
    ($this->stockAt)($location, '10');
    $orderId = ($this->createOrder)(4);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$orderId}/stock-plan")
        ->assertOk()
        ->assertJsonPath('data.0.state', 'resolved')
        ->assertJsonPath('data.0.stockLocationId', $location->id)
        ->assertJsonPath('data.0.quantity', '4.000');
});

it('annonce les emplacements a departager', function (): void {
    ($this->stockAt)(($this->location)('A-1'), '10');
    ($this->stockAt)(($this->location)('B-2'), '10');
    $orderId = ($this->createOrder)(4);

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson("/api/v1/orders/{$orderId}/stock-plan")
        ->assertOk()
        ->assertJsonPath('data.0.state', 'ambiguous')
        ->assertJsonPath('data.0.stockLocationId', null)
        ->assertJsonCount(2, 'data.0.locations');
});
