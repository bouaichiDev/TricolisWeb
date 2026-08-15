<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;
use App\Shared\Database\MorphMap;

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

    $this->servicePayload = [
        'serviceId' => $this->service->id, 'addressId' => $this->address->id,
        'serviceNumber' => 'SRV-1', 'sequence' => 1, 'requestedDate' => now()->toDateString(),
        'quantity' => 1, 'unit' => 'delivery', 'requiredTimeMinutes' => 30, 'remainingTimeMinutes' => 30,
        'weight' => 0, 'volume' => 0, 'packageCount' => 0,
        'customerUnitPrice' => 0, 'customerTotalPrice' => 0, 'providerUnitCost' => 0, 'providerTotalCost' => 0,
        'status' => 'draft',
    ];

    $this->payload = fn (array $overrides = []): array => array_merge([
        'customerId' => $this->customer->id,
        'agencyId' => $this->agency->id,
        'orderDate' => now()->toISOString(),
        'lines' => [['name' => 'Canapé', 'quantity' => 4, 'weight' => 10]],
        'services' => [$this->servicePayload],
    ], $overrides);
});

describe('order numbering', function (): void {
    it('assigns sequential numbers without reusing one', function (): void {
        $first = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', ($this->payload)())->assertCreated();

        $second = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', ($this->payload)(['services' => [['serviceNumber' => 'SRV-2'] + $this->servicePayload]]))
            ->assertCreated();

        $year = now()->format('Y');
        expect($first->json('data.orderNumber'))->toBe("ORD-$year-000001")
            ->and($second->json('data.orderNumber'))->toBe("ORD-$year-000002");
    });
});

describe('full order creation', function (): void {
    it('creates lines, packages, allocations and services in one request', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', ($this->payload)([
                'lines' => [['name' => 'Canapé', 'quantity' => 4, 'weight' => 10]],
                'packages' => [
                    ['key' => 'p1', 'barcode' => 'PKG-A', 'lines' => [['lineKey' => '0', 'quantity' => 3]]],
                    ['key' => 'p2', 'parentKey' => 'p1', 'barcode' => 'PKG-B'],
                ],
                'services' => [$this->servicePayload + ['packages' => [['packageKey' => 'p1', 'quantity' => 1]]]],
            ]))
            ->assertCreated();

        $order = Order::findOrFail($response->json('data.id'));

        expect($order->lines)->toHaveCount(1)
            ->and($order->packages)->toHaveCount(2)
            ->and($order->orderServices)->toHaveCount(1)
            ->and($order->packages->firstWhere('barcode', 'PKG-B')->parent_package_id)
            ->toBe($order->packages->firstWhere('barcode', 'PKG-A')->id);

        $this->assertDatabaseHas('package_order_lines', ['quantity' => 3]);
        $this->assertDatabaseHas('order_service_packages', ['quantity' => 1]);
    });

    it('rolls everything back when a sub-resource fails', function (): void {
        $foreignService = Service::factory()->create();
        $ordersBefore = Order::count();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', ($this->payload)([
                'services' => [['serviceId' => $foreignService->id] + $this->servicePayload],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('services.0.serviceId');

        expect(Order::count())->toBe($ordersBefore);
        $this->assertDatabaseCount('order_lines', 0);
    });

    it('reports the exact path of the faulty field', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', ($this->payload)([
                'packages' => [['key' => 'p1', 'lines' => [['lineKey' => 'ghost', 'quantity' => 1]]]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('packages.0.lines.0.lineKey');
    });

    it('copies catalog data into the line and keeps it after the article changes', function (): void {
        $catalog = CustomerCatalog::factory()->forCustomer($this->customer)->create();
        $item = CustomerCatalogItem::factory()->forCatalog($catalog)->create(['name' => 'Table origine', 'weight' => 8]);

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', ($this->payload)([
                'lines' => [['catalogItemId' => $item->id, 'quantity' => 2]],
            ]))->assertCreated();

        $item->update(['name' => 'Table renommée', 'weight' => 99]);

        $line = Order::findOrFail($response->json('data.id'))->lines()->firstOrFail();
        expect($line->name)->toBe('Table origine')
            ->and((float) $line->weight)->toBe(8.0)
            ->and($line->catalog_item_id)->toBe($item->id);
    });

    it('refuses an article from another customer', function (): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $catalog = CustomerCatalog::factory()->forCustomer($otherCustomer)->create();
        $item = CustomerCatalogItem::factory()->forCatalog($catalog)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', ($this->payload)(['lines' => [['catalogItemId' => $item->id, 'quantity' => 1]]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines.0.catalogItemId');
    });
});

describe('order status', function (): void {
    it('follows the workflow and refuses an invalid transition', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'completed'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    });

    it('refuses a status produced by planning or invoicing', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'planned'])
            ->assertStatus(422);
    });

    it('requires a reason to cancel', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422)->assertJsonValidationErrors('reasonText');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'cancelled', 'reasonText' => 'Client absent'])
            ->assertOk();
    });

    it('exposes the history from the audit trail', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create(['created_by' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed'])->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$order->id}/history")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'status_changed');
    });

    it('freezes the content of an engaged order', function (): void {
        $order = Order::factory()->forOrganization($this->organization)
            ->withStatus(OrderStatus::PLANNED)->create(['created_by' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$order->id}/lines", ['name' => 'Ajout tardif', 'quantity' => 1])
            ->assertStatus(409);
    });
});

describe('order duplication', function (): void {
    it('copies the content but never the number nor the status', function (): void {
        $created = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', ($this->payload)([
                'packages' => [['key' => 'p1', 'barcode' => 'PKG-SRC']],
            ]))->assertCreated();

        $source = Order::findOrFail($created->json('data.id'));
        $source->update(['status' => OrderStatus::CONFIRMED]);

        $copy = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$source->id}/duplicate")
            ->assertCreated();

        expect($copy->json('data.orderNumber'))->not->toBe($source->order_number)
            ->and($copy->json('data.status'))->toBe('draft')
            ->and($copy->json('data.lines'))->toHaveCount(1)
            ->and($copy->json('data.packages'))->toHaveCount(1)
            // Le code-barres identifie un colis physique : il n'est pas copié.
            ->and($copy->json('data.packages.0.barcode'))->toBeNull();
    });

    it('honours the copy options', function (): void {
        $created = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', ($this->payload)())->assertCreated();

        $copy = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/orders/{$created->json('data.id')}/duplicate", ['services' => false])
            ->assertCreated();

        expect($copy->json('data.services'))->toHaveCount(0);
    });
});

describe('order isolation', function (): void {
    it('hides an order from another organization', function (): void {
        $foreign = Order::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders/{$foreign->id}")
            ->assertNotFound();
    });

    it('filters by customer and by status', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/orders', ($this->payload)())->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/orders?customerId={$this->customer->id}&status=draft")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/orders?status=completed')
            ->assertOk()->assertJsonCount(0, 'data');
    });

    it('rejects a forbidden sort column', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/orders?sort=internal_remark')
            ->assertStatus(422);
    });
});
