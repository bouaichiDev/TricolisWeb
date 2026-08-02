<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Modules\Stock\Models\StockReservation;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->item = StockItem::factory()->forCustomer($this->customer)->create();
    $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $depot = Depot::factory()->create(['agency_id' => $agency->id]);
    $this->location = StockLocation::factory()->forDepot($depot)->create();

    $this->order = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $this->customer->id]);
    $this->orderLine = OrderLine::factory()->create(['order_id' => $this->order->id]);

    $this->balance = StockBalance::factory()->at($this->item, $this->location)->withQuantity(20)->create();

    $this->payload = fn (array $o = []): array => array_merge([
        'stockItemId' => $this->item->id,
        'stockLocationId' => $this->location->id,
        'orderLineId' => $this->orderLine->id,
        'quantity' => 5,
        'status' => 'active',
    ], $o);
});

describe('stock reservations creation', function (): void {
    it('reserves stock and updates the balance', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)())
            ->assertCreated()->assertJsonPath('data.quantity', '5.000');

        $balance = $this->balance->fresh();

        expect($balance->quantity)->toBe('20.000')
            ->and($balance->reserved_quantity)->toBe('5.000')
            ->and($balance->available_quantity)->toBe('15.000');
    });

    it('refuses to reserve more than available', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)(['quantity' => 25]))
            ->assertStatus(409);

        expect($this->balance->fresh()->reserved_quantity)->toBe('0.000');
    });

    it('accounts for stock already reserved', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)(['quantity' => 18]))
            ->assertCreated();

        // 20 − 18 = 2 disponibles : 5 de plus est refusé.
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)(['quantity' => 5]))
            ->assertStatus(409);
    });

    it('refuses an order line of another customer', function (): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $otherOrder = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $otherCustomer->id]);
        $foreignLine = OrderLine::factory()->create(['order_id' => $otherOrder->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)(['orderLineId' => $foreignLine->id]))
            ->assertStatus(422)->assertJsonValidationErrors('orderLineId');
    });

    it('refuses an item or a location from another organization', function (): void {
        $foreignItem = StockItem::factory()->create();
        $foreignLocation = StockLocation::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)(['stockItemId' => $foreignItem->id]))
            ->assertStatus(422)->assertJsonValidationErrors('stockItemId');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)(['stockLocationId' => $foreignLocation->id]))
            ->assertStatus(422)->assertJsonValidationErrors('stockLocationId');
    });

    it('refuses a zero or negative quantity', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)(['quantity' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
    });
});

describe('stock reservations release', function (): void {
    it('releases without deleting and gives the quantity back', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/stock-reservations/$id/release", ['status' => 'released'])
            ->assertOk()->assertJsonPath('data.status', 'released');

        // La ligne subsiste : c'est ce qui permet de retracer l'immobilisation.
        $this->assertDatabaseHas('stock_reservations', ['id' => $id]);
        expect(StockReservation::find($id)->released_at)->not->toBeNull();

        $balance = $this->balance->fresh();
        expect($balance->reserved_quantity)->toBe('0.000')
            ->and($balance->available_quantity)->toBe('20.000');
    });

    it('refuses a double release', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/stock-reservations/$id/release", ['status' => 'released'])->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/stock-reservations/$id/release", ['status' => 'released'])
            ->assertStatus(409);

        // Le solde n'a pas ete decremente deux fois.
        expect($this->balance->fresh()->reserved_quantity)->toBe('0.000');
    });

    it('requires a status on release', function (): void {
        $reservation = StockReservation::factory()->create([
            'stock_item_id' => $this->item->id,
            'stock_location_id' => $this->location->id,
            'order_line_id' => $this->orderLine->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/stock-reservations/{$reservation->id}/release", [])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    });
});

describe('stock reservations update', function (): void {
    it('updates the status only', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/stock-reservations/$id", ['status' => 'confirmed'])
            ->assertOk()->assertJsonPath('data.status', 'confirmed');

        // La quantite n'est pas modifiable : le solde reste intact.
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/stock-reservations/$id", ['status' => 'confirmed', 'quantity' => 99])
            ->assertOk();

        expect(StockReservation::find($id)->quantity)->toBe('5.000')
            ->and($this->balance->fresh()->reserved_quantity)->toBe('5.000');
    });

    it('exposes no DELETE route', function (): void {
        $reservation = StockReservation::factory()->create([
            'stock_item_id' => $this->item->id,
            'stock_location_id' => $this->location->id,
            'order_line_id' => $this->orderLine->id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-reservations/{$reservation->id}")->assertStatus(405);
    });
});

describe('stock reservations and balances read', function (): void {
    it('hides a reservation from another organization', function (): void {
        $foreign = StockReservation::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/stock-reservations/{$foreign->id}")->assertNotFound();
    });

    it('lists balances in read only', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-balances')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$this->customer->id}/stock-balances")->assertOk();

        // Aucun CRUD public sur les soldes.
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-balances', ['quantity' => 999])->assertStatus(405);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/stock-balances/{$this->balance->id}", ['quantity' => 999])
            ->assertStatus(405);
    });

    it('filters balances on availability', function (): void {
        $empty = StockItem::factory()->forCustomer($this->customer)->create();
        StockBalance::factory()->at($empty, $this->location)->withQuantity(0)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-balances?availableOnly=1')->assertOk()->assertJsonCount(1, 'data');
    });

    it('audits reservation and release', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-reservations', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stock_reservation.created', 'entity_type' => 'stock_reservation', 'entity_id' => $id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/stock-reservations/$id/release", ['status' => 'released'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_reservation.released', 'entity_id' => $id]);
    });
});
