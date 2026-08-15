<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
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
    $this->item = StockItem::factory()->forCustomer($this->customer)->create();
    $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $this->depot = Depot::factory()->create(['agency_id' => $agency->id]);
    $this->from = StockLocation::factory()->forDepot($this->depot)->create();
    $this->to = StockLocation::factory()->forDepot($this->depot)->create();

    $this->payload = fn (array $o = []): array => array_merge([
        'stockItemId' => $this->item->id,
        'movementType' => 'inbound',
        'quantity' => 10,
        'destinationLocationId' => $this->to->id,
    ], $o);
});

describe('stock movements structure', function (): void {
    it('creates an inbound movement and increases the balance', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)())
            ->assertCreated()->assertJsonPath('data.quantity', '10.000');

        $balance = StockBalance::where('stock_item_id', $this->item->id)
            ->where('stock_location_id', $this->to->id)->firstOrFail();

        expect($balance->quantity)->toBe('10.000')
            ->and($balance->available_quantity)->toBe('10.000');
    });

    it('refuses a movement without source nor destination', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', [
                'stockItemId' => $this->item->id, 'movementType' => 'x', 'quantity' => 1,
            ])
            ->assertStatus(422)->assertJsonValidationErrors('sourceLocationId');
    });

    it('refuses identical source and destination', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)([
                'sourceLocationId' => $this->to->id,
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('destinationLocationId');
    });

    it('refuses a movement across two depots', function (): void {
        $otherDepot = Depot::factory()->create(['agency_id' => $this->depot->agency_id]);
        $otherLocation = StockLocation::factory()->forDepot($otherDepot)->create();

        StockBalance::factory()->at($this->item, $this->from)->withQuantity(50)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)([
                'sourceLocationId' => $this->from->id,
                'destinationLocationId' => $otherLocation->id,
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('destinationLocationId');
    });

    it('refuses a zero or negative quantity', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)(['quantity' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
    });

    it('refuses an item from another organization', function (): void {
        $foreignItem = StockItem::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)(['stockItemId' => $foreignItem->id]))
            ->assertStatus(422)->assertJsonValidationErrors('stockItemId');
    });

    it('refuses a location from another organization', function (): void {
        $foreignLocation = StockLocation::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)([
                'destinationLocationId' => $foreignLocation->id,
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('destinationLocationId');
    });
});

describe('stock movements quantities', function (): void {
    it('transfers between two locations of the same depot', function (): void {
        StockBalance::factory()->at($this->item, $this->from)->withQuantity(30)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)([
                'movementType' => 'transfer',
                'quantity' => 12,
                'sourceLocationId' => $this->from->id,
            ]))
            ->assertCreated();

        $source = StockBalance::where('stock_location_id', $this->from->id)->firstOrFail();
        $destination = StockBalance::where('stock_location_id', $this->to->id)->firstOrFail();

        expect($source->quantity)->toBe('18.000')
            ->and($destination->quantity)->toBe('12.000');
    });

    it('refuses to move more than available', function (): void {
        StockBalance::factory()->at($this->item, $this->from)->withQuantity(5)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)([
                'quantity' => 10,
                'sourceLocationId' => $this->from->id,
                'destinationLocationId' => null,
                'movementType' => 'outbound',
            ]))
            ->assertStatus(409);

        expect(StockBalance::where('stock_location_id', $this->from->id)->value('quantity'))->toBe('5.000');
    });

    it('refuses to move reserved stock out', function (): void {
        // 10 en stock dont 8 reserves : seuls 2 sont disponibles.
        StockBalance::factory()->at($this->item, $this->from)->withQuantity(10, 8)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)([
                'quantity' => 5,
                'sourceLocationId' => $this->from->id,
                'destinationLocationId' => null,
                'movementType' => 'outbound',
            ]))
            ->assertStatus(409);
    });

    it('creates the balance on first inbound', function (): void {
        expect(StockBalance::where('stock_item_id', $this->item->id)->count())->toBe(0);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)())->assertCreated();

        expect(StockBalance::where('stock_item_id', $this->item->id)->count())->toBe(1);
    });
});

describe('stock movements source entity', function (): void {
    it('accepts a known morph alias', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)([
                'sourceEntityType' => 'order',
                'sourceEntityId' => $order->id,
            ]))
            ->assertCreated()->assertJsonPath('data.sourceEntityType', 'order');
    });

    it('refuses an unknown type and a php class name', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)([
                'sourceEntityType' => 'unknown_thing',
                'sourceEntityId' => '01JC0000000000000000000001',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('sourceEntityType');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)([
                'sourceEntityType' => 'App\\Modules\\Orders\\Models\\Order',
                'sourceEntityId' => '01JC0000000000000000000001',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('sourceEntityType');
    });
});

describe('stock movements immutability', function (): void {
    it('exposes no PATCH nor DELETE route', function (): void {
        $movement = StockMovement::factory()->forItem($this->item)->create([
            'destination_location_id' => $this->to->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/stock-movements/{$movement->id}", ['quantity' => 1])
            ->assertStatus(405);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-movements/{$movement->id}")
            ->assertStatus(405);
    });

    it('has no updated_at column', function (): void {
        expect(Schema::getColumnListing('stock_movements'))->not->toContain('updated_at')
            ->and(Schema::getColumnListing('stock_movements'))->not->toContain('legacy_id');
    });
});

describe('stock movements read and audit', function (): void {
    it('lists only the movements of the active organization, newest first', function (): void {
        StockMovement::factory()->forItem($this->item)->create([
            'destination_location_id' => $this->to->id, 'created_at' => '2026-09-01 08:00:00',
        ]);
        StockMovement::factory()->forItem($this->item)->create([
            'destination_location_id' => $this->to->id, 'created_at' => '2026-09-05 08:00:00',
        ]);
        StockMovement::factory(2)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-movements')->assertOk()->assertJsonCount(2, 'data');

        expect($response->json('data.0.createdAt'))->toStartWith('2026-09-05');
    });

    it('hides a movement from another organization', function (): void {
        $foreign = StockMovement::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/stock-movements/{$foreign->id}")->assertNotFound();
    });

    it('audits creation', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', ($this->payload)())->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stock_movement.created',
            'entity_type' => 'stock_movement',
            'entity_id' => $response->json('data.id'),
        ]);
    });
});
