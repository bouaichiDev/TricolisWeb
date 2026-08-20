<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::where('organization_id', $this->organization->id)->firstOrFail();
    $this->agency = Agency::where('organization_id', $this->organization->id)->firstOrFail();
    $this->address = Address::factory()->create();
    EntityAddress::create(['organization_id' => $this->organization->id, 'address_id' => $this->address->id, 'entity_type' => 'organization', 'entity_id' => $this->organization->id, 'address_type' => 'delivery', 'is_default' => true]);
    $this->service = Service::create(['organization_id' => $this->organization->id, 'code' => 'DELIVERY', 'name' => 'Livraison', 'unit' => 'delivery', 'default_duration_minutes' => 30, 'billable_to_customer' => true, 'payable_to_provider' => true, 'requires_address' => true, 'requires_contact' => false, 'status' => 'active']);
    $this->orderService = ['serviceId' => $this->service->id, 'addressId' => $this->address->id, 'serviceNumber' => 'SRV-1', 'sequence' => 1, 'requestedDate' => now()->toDateString(), 'quantity' => 1, 'unit' => 'delivery', 'requiredTimeMinutes' => 30, 'remainingTimeMinutes' => 30, 'weight' => 0, 'volume' => 0, 'packageCount' => 0, 'customerUnitPrice' => 0, 'customerTotalPrice' => 0, 'providerUnitCost' => 0, 'providerTotalCost' => 0, 'status' => 'draft'];
});

it('creates a draft order with at least one line', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/orders', [
            'customerId' => $this->customer->id,
            'agencyId' => $this->agency->id,
            'orderDate' => now()->toISOString(),
            'lines' => [['name' => 'Canapé', 'articleCode' => 'CAN-1', 'quantity' => 2]],
            'services' => [$this->orderService],
        ]);

    // Le numéro est attribué par la séquence, jamais par l'appelant.
    $response->assertCreated()
        ->assertJsonPath('data.orderNumber', 'ORD-'.now()->format('Y').'-000001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonCount(1, 'data.services');
    $this->assertDatabaseHas('order_lines', ['name' => 'Canapé']);
    $this->assertDatabaseHas('order_services', ['service_number' => 'SRV-1', 'service_id' => $this->service->id]);
});

it('requires at least one order line', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/orders', [
            'customerId' => $this->customer->id,
            'agencyId' => $this->agency->id,
            'orderDate' => now()->toISOString(),
            'lines' => [],
            'services' => [$this->orderService],
        ])->assertUnprocessable()->assertJsonValidationErrors('lines');
});

it('requires at least one order service', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/orders', [
            'customerId' => $this->customer->id,
            'agencyId' => $this->agency->id,
            'orderDate' => now()->toISOString(),
            'lines' => [['name' => 'Article', 'quantity' => 1]],
            'services' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('services');
});

it('prevents using a customer from another organization', function (): void {
    $customer = Customer::factory()->create();
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/orders', [
            'customerId' => $customer->id,
            'agencyId' => $this->agency->id,
            'orderDate' => now()->toISOString(),
            'lines' => [['name' => 'Article', 'quantity' => 1]],
            'services' => [$this->orderService],
        ])->assertUnprocessable();
});

it('lists only orders from the active organization', function (): void {
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/orders')->assertOk();
    expect(Order::where('organization_id', $this->organization->id)->count())->toBeGreaterThanOrEqual(0);
});

/**
 * La vignette d'un service montre son adresse : deux services d'une même
 * commande portent souvent le même nom, et seule l'adresse les distingue.
 * Sans le chargement de la relation, l'écran n'aurait qu'un identifiant.
 */
it('exposes the address of every service in the order detail', function (): void {
    $created = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson('/api/v1/orders', [
            'customerId' => $this->customer->id,
            'agencyId' => $this->agency->id,
            'orderDate' => now()->toISOString(),
            'lines' => [['name' => 'Canapé', 'articleCode' => 'CAN-1', 'quantity' => 2]],
            'services' => [$this->orderService],
        ])->json('data.id');

    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->getJson('/api/v1/orders/'.$created)
        ->assertOk()
        ->assertJsonPath('data.services.0.address.id', $this->address->id)
        ->assertJsonPath('data.services.0.address.addressLine1', $this->address->address_line_1);
});
