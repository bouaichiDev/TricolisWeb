<?php

use App\Modules\CustomerPortal\Support\CustomerPortalRoles;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalAfterDelivery;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalTracking;

/**
 * Preuves de livraison d'une commande et d'un service — lecture seule (§18, §32).
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->order = CustomerPortalOrders::order('ikea');
    $this->service = $this->order->orderServices()->first();
    $this->headers = CustomerPortal::headers('viewer@ikea.test');
});

it('lists the proofs of an order and of one of its services', function (): void {
    $proof = CustomerPortalAfterDelivery::proof($this->order);

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/orders/{$this->order->id}/proofs-of-delivery")->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $proof->id)
        ->assertJsonPath('data.0.recipientName', 'Anna Berg')
        ->assertJsonPath('data.0.service.id', $this->service->id);

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/orders/{$this->order->id}/services/{$this->service->id}/proofs-of-delivery")->assertOk()
        ->assertJsonPath('data.0.id', $proof->id);
});

it('hides a proof whose service belongs to another order', function (): void {
    CustomerPortalAfterDelivery::proof($this->order, serviceId: CustomerPortalOrders::order('qoqa')->orderServices()->value('id'));

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/orders/{$this->order->id}/proofs-of-delivery")->assertOk()->assertJsonCount(0, 'data');
});

it('answers 404 for the order or the service of another customer', function (): void {
    $qoqa = CustomerPortalOrders::order('qoqa');
    CustomerPortalAfterDelivery::proof($qoqa);

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/orders/{$qoqa->id}/proofs-of-delivery")->assertNotFound();
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/orders/{$this->order->id}/services/{$qoqa->orderServices()->value('id')}/proofs-of-delivery")->assertNotFound();
});

it('offers no write access at all', function (string $method): void {
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->json($method, "/api/v1/customer/orders/{$this->order->id}/proofs-of-delivery", ['recipientName' => 'Pirate'])
        ->assertStatus(405);
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it('requires the proof of delivery permission', function (): void {
    CustomerPortalTracking::giveRole('viewer@ikea.test', CustomerPortalRoles::STOCK_MANAGER);

    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson("/api/v1/customer/orders/{$this->order->id}/proofs-of-delivery")->assertForbidden();
});
