<?php

use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Test critique de la Phase 2 : IKEA ne touche jamais une commande QOQA.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->ikea = CustomerPortal::headers('admin@ikea.test');
    $this->qoqaOrder = CustomerPortalOrders::order('qoqa');
});

it('shows its own order', function (): void {
    $order = CustomerPortalOrders::order('ikea');

    $this->withHeaders($this->ikea)->getJson("/api/v1/customer/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.reference', $order->order_number);
});

it('answers 404 on every access to a QOQA order', function (string $method, string $suffix, array $payload): void {
    $this->withHeaders($this->ikea)->json($method, "/api/v1/customer/orders/{$this->qoqaOrder->id}{$suffix}", $payload)
        ->assertNotFound();

    expect($this->qoqaOrder->fresh()->status->value)->toBe($this->qoqaOrder->status->value);
})->with([
    'show' => ['GET', '', []],
    'duplicate' => ['POST', '/duplicate', []],
    'cancel' => ['POST', '/cancel', ['reason' => 'pirate']],
    'services' => ['GET', '/services', []],
    'packages' => ['GET', '/packages', []],
    'lines' => ['GET', '/lines', []],
    'documents' => ['GET', '/documents', []],
    'history' => ['GET', '/history', []],
]);

it('rejects a full valid update aimed at a QOQA order with 404', function (): void {
    $payload = [...CustomerPortalOrders::payload(), 'updatedAt' => $this->qoqaOrder->updated_at->toIso8601String()];

    $this->withHeaders($this->ikea)->putJson("/api/v1/customer/orders/{$this->qoqaOrder->id}", $payload)->assertNotFound();
});

it('lists only the orders of the current customer', function (): void {
    $references = collect($this->withHeaders($this->ikea)->getJson('/api/v1/customer/orders')->assertOk()->json('data'))
        ->pluck('reference');

    expect($references)->toContain(CustomerPortalOrders::order('ikea')->order_number)
        ->not->toContain($this->qoqaOrder->order_number);
});
