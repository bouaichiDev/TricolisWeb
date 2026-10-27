<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Création d'une commande depuis le portail, par le service métier commun.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->headers = CustomerPortal::headers('orders@ikea.test');
});

it('creates a full order for the current customer, from the portal', function (): void {
    $response = $this->withHeaders($this->headers)->postJson('/api/v1/customer/orders', CustomerPortalOrders::payload())
        ->assertCreated()
        ->assertJsonPath('data.customerReference', 'IKEA-4587')
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonCount(1, 'data.packages')
        ->assertJsonPath('data.services.0.requestedFrom', '08:00')
        ->assertJsonPath('data.services.0.contacts.0.firstName', 'Anna')
        ->assertJsonPath('data.actions.canEdit', true);

    $order = Order::findOrFail($response->json('data.id'));

    expect($order->customer_id)->toBe(CustomerPortal::customer('ikea')->id)
        ->and($order->source)->toBe(OrderSource::CUSTOMER_PORTAL)
        ->and($order->agency_id)->not->toBeNull()
        ->and($order->orderServices()->first()->servicePackages()->count())->toBe(1);
});

/*
 * Phase 7 : les champs imposés par le serveur ne sont plus ignorés en silence,
 * ils sont refusés (422) — la tentative devient visible, et rien n'est créé.
 */
it('refuses a payload that chooses the customer, the source or internal fields', function (): void {
    $qoqa = CustomerPortal::customer('qoqa');
    $count = Order::count();

    $this->withHeaders($this->headers)->postJson('/api/v1/customer/orders', CustomerPortalOrders::payload([
        'customerId' => $qoqa->id,
        'source' => 'internal',
        'internalRemark' => 'pirate',
        'status' => 'completed',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['customerId', 'source', 'internalRemark', 'status']);

    expect(Order::count())->toBe($count);
});

it('imposes the customer and the source itself', function (): void {
    $response = $this->withHeaders($this->headers)->postJson('/api/v1/customer/orders', CustomerPortalOrders::payload())->assertCreated();
    $order = Order::findOrFail($response->json('data.id'));

    expect($order->customer_id)->toBe(CustomerPortal::customer('ikea')->id)
        ->and($order->source)->toBe(OrderSource::CUSTOMER_PORTAL)
        ->and($order->internal_remark)->toBeNull();
});

it('exposes no internal data in the detail', function (): void {
    $json = $this->withHeaders($this->headers)->postJson('/api/v1/customer/orders', CustomerPortalOrders::payload())->json('data');

    expect($json)->not->toHaveKeys(['internalRemark', 'agencyId', 'organizationId', 'createdBy'])
        ->and($json['lines'][0])->not->toHaveKeys(['purchasePrice', 'sellingPrice'])
        ->and($json['services'][0])->not->toHaveKeys(['providerUnitCost', 'providerTotalCost', 'customerUnitPrice']);
});

it('mixes catalogue articles and free lines', function (): void {
    $payload = CustomerPortalOrders::payload();
    $payload['lines'][] = ['name' => 'Article hors catalogue', 'quantity' => 1];

    $response = $this->withHeaders($this->headers)->postJson('/api/v1/customer/orders', $payload)
        ->assertCreated()
        ->assertJsonCount(3, 'data.lines');

    expect(collect($response->json('data.lines'))->pluck('catalogItemId')->filter()->count())->toBe(2);
});

it('refuses an article from another customer catalogue', function (): void {
    $foreign = CustomerCatalogItem::factory()
        ->forCatalog(CustomerCatalog::factory()->forCustomer(CustomerPortal::customer('qoqa'))->create())
        ->create();
    $payload = CustomerPortalOrders::payload();
    $payload['lines'][0]['catalogItemId'] = $foreign->id;

    $this->withHeaders($this->headers)->postJson('/api/v1/customer/orders', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines.0.catalogItemId');
});

it('refuses an address that belongs to another customer', function (): void {
    $payload = CustomerPortalOrders::payload();
    $payload['services'][0]['addressId'] = CustomerPortalOrders::addressOf('qoqa');

    $this->withHeaders($this->headers)->postJson('/api/v1/customer/orders', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'INVALID_ORDER_SERVICE');
});

it('accepts a one-off recipient address and uses it as contact', function (): void {
    $payload = CustomerPortalOrders::payload();
    unset($payload['services'][0]['addressId'], $payload['services'][0]['contacts']);
    $payload['services'][0]['address'] = [
        'firstName' => 'Lea', 'lastName' => 'Muller', 'addressLine1' => '5 Rue du Lac',
        'postalCode' => '1003', 'city' => 'Lausanne', 'country' => 'ch', 'phone' => '+41 79 000 00 00',
    ];

    $this->withHeaders($this->headers)->postJson('/api/v1/customer/orders', $payload)
        ->assertCreated()
        ->assertJsonPath('data.services.0.address.city', 'Lausanne')
        ->assertJsonPath('data.services.0.contacts.0.firstName', 'Lea');
});

it('rolls everything back when one part is invalid', function (): void {
    $orders = Order::count();
    $addresses = Address::count();
    $payload = CustomerPortalOrders::payload();
    unset($payload['services'][0]['addressId']);
    $payload['services'][0]['address'] = ['firstName' => 'Test', 'lastName' => 'Rollback', 'addressLine1' => '1 Rue', 'postalCode' => '1000', 'city' => 'Lausanne', 'country' => 'CH'];
    $payload['services'][0]['packages'] = [['packageKey' => 'UNKNOWN']];

    $this->withHeaders($this->headers)->postJson('/api/v1/customer/orders', $payload)->assertUnprocessable();

    expect(Order::count())->toBe($orders)->and(Address::count())->toBe($addresses);
});
