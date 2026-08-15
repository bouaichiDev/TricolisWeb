<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Packages\Models\Package;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->payload = fn (array $o = []): array => array_merge([
        'customerId' => $this->customer->id,
        'articleCode' => 'ART-0001',
        'status' => 'active',
    ], $o);
});

describe('stock items creation', function (): void {
    it('creates an item for a customer of the active organization', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-items', ($this->payload)(['barcode' => '3401234567890']))
            ->assertCreated()
            ->assertJsonPath('data.articleCode', 'ART-0001');

        $this->assertDatabaseHas('stock_items', [
            'id' => $response->json('data.id'),
            'customer_id' => $this->customer->id,
        ]);
    });

    it('refuses a customer from another organization', function (): void {
        $foreign = Customer::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-items', ($this->payload)(['customerId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('customerId');
    });

    it('refuses a catalog item of another customer', function (): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $catalog = CustomerCatalog::factory()->create(['customer_id' => $otherCustomer->id]);
        $catalogItem = CustomerCatalogItem::factory()->create(['catalog_id' => $catalog->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-items', ($this->payload)(['catalogItemId' => $catalogItem->id]))
            ->assertStatus(422)->assertJsonValidationErrors('catalogItemId');
    });

    it('refuses a duplicated article code for the same customer', function (): void {
        StockItem::factory()->forCustomer($this->customer)->create(['article_code' => 'ART-DUP']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-items', ($this->payload)(['articleCode' => 'ART-DUP']))
            ->assertStatus(422)->assertJsonValidationErrors('articleCode');
    });

    it('allows the same article code for another customer', function (): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        StockItem::factory()->forCustomer($otherCustomer)->create(['article_code' => 'ART-DUP']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-items', ($this->payload)(['articleCode' => 'ART-DUP']))
            ->assertCreated();
    });

    it('refuses a duplicated barcode for the same customer but allows it elsewhere', function (): void {
        StockItem::factory()->forCustomer($this->customer)->create(['barcode' => '3401234567890']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-items', ($this->payload)(['barcode' => '3401234567890']))
            ->assertStatus(422)->assertJsonValidationErrors('barcode');

        // Le code-barres n'est pas unique globalement : deux clients peuvent
        // employer le meme code interne.
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$otherCustomer->id}/stock-items", [
                'articleCode' => 'ART-OTHER',
                'barcode' => '3401234567890',
                'status' => 'active',
            ])
            ->assertCreated();
    });

    it('creates through the customer route', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/stock-items", [
                'articleCode' => 'ART-VIA-CUSTOMER',
                'status' => 'active',
            ])
            ->assertCreated()->assertJsonPath('data.customerId', $this->customer->id);
    });
});

describe('stock items schema', function (): void {
    it('carries neither organization, depot, quantity nor location', function (): void {
        $columns = Schema::getColumnListing('stock_items');

        expect($columns)->not->toContain('organization_id')
            ->and($columns)->not->toContain('depot_id')
            ->and($columns)->not->toContain('quantity')
            ->and($columns)->not->toContain('stock_location_id')
            ->and($columns)->not->toContain('unit')
            ->and($columns)->not->toContain('minimum_quantity');

        expect(Schema::hasTable('warehouses'))->toBeFalse()
            ->and(Schema::hasTable('stock_zones'))->toBeFalse()
            ->and(Schema::hasTable('package_location_histories'))->toBeFalse();
    });

    it('now enforces the package foreign key left pending in phase 2', function (): void {
        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
        $depot = Depot::factory()->create(['agency_id' => $agency->id]);
        $location = StockLocation::factory()->forDepot($depot)->create();

        $order = Order::factory()->forOrganization($this->organization)->create();
        $package = Package::factory()->create([
            'order_id' => $order->id,
            'current_stock_location_id' => $location->id,
        ]);

        expect($package->fresh()->current_stock_location_id)->toBe($location->id);
    });
});

describe('stock items read, update and delete', function (): void {
    it('reads, updates and deletes an unused item', function (): void {
        $item = StockItem::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/stock-items/{$item->id}")->assertOk()
            ->assertJsonPath('data.id', $item->id);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/stock-items/{$item->id}", ['status' => 'archived'])
            ->assertOk()->assertJsonPath('data.status', 'archived');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-items/{$item->id}")->assertNoContent();

        $this->assertDatabaseMissing('stock_items', ['id' => $item->id]);
    });

    it('refuses to delete an item that carries stock', function (): void {
        $item = StockItem::factory()->forCustomer($this->customer)->create();
        $location = StockLocation::factory()->create();
        StockBalance::factory()->at($item, $location)->withQuantity(5)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-items/{$item->id}")->assertStatus(409);

        $this->assertDatabaseHas('stock_items', ['id' => $item->id]);
    });

    it('refuses to delete an item that has movements', function (): void {
        $item = StockItem::factory()->forCustomer($this->customer)->create();
        StockMovement::factory()->forItem($item)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-items/{$item->id}")->assertStatus(409);
    });

    it('hides an item from another organization', function (): void {
        $foreign = StockItem::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/stock-items/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-items/{$foreign->id}")->assertNotFound();
    });
});

describe('stock items list', function (): void {
    it('lists only the items of the active organization', function (): void {
        StockItem::factory(2)->forCustomer($this->customer)->create();
        StockItem::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-items')->assertOk()->assertJsonCount(2, 'data');
    });

    it('searches, filters and rejects a forbidden sort', function (): void {
        StockItem::factory()->forCustomer($this->customer)->create([
            'article_code' => 'ZZZ-1', 'description' => 'Palette Europe', 'status' => 'archived',
        ]);
        StockItem::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-items?search=ZZZ')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-items?search=Palette')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-items?status=archived')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$this->customer->id}/stock-items")->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-items?sort=customer_id')->assertStatus(422);
    });
});

describe('stock items audit', function (): void {
    it('audits creation, update and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-items', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stock_item.created', 'entity_type' => 'stock_item', 'entity_id' => $id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/stock-items/$id", ['description' => 'Modifié'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_item.updated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-items/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_item.deleted', 'entity_id' => $id]);
    });
});
