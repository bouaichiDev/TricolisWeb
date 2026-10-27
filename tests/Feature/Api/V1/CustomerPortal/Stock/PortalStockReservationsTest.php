<?php

use App\Modules\Stock\Models\StockReservation;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalStock;

/**
 * Réservations des articles du client pour ses commandes, en lecture seule
 * (§17-§18, §30 « Reservations »).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $this->a = CustomerPortalStock::location('A-01');
    $this->billy = CustomerPortalStock::item('ikea', 'ART-001');
    CustomerPortalStock::receive($this->billy, $this->a, '50');
    $this->reservation = CustomerPortalStock::reserve($this->billy, $this->a, CustomerPortalStock::line('ikea'), '10');
    $qoqaItem = CustomerPortalStock::item('qoqa', 'QOQA-001');
    CustomerPortalStock::receive($qoqaItem, $this->a, '30');
    $this->qoqa = CustomerPortalStock::reserve($qoqaItem, $this->a, CustomerPortalStock::line('qoqa'), '5');
    $this->headers = CustomerPortal::headers('viewer@ikea.test');
});

it('lists its reservations with the linked order and order line', function (): void {
    $order = CustomerPortalOrders::order('ikea');

    $this->withHeaders($this->headers)->getJson('/api/v1/customer/stock/reservations')->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $this->reservation->id)
        ->assertJsonPath('data.0.quantity', '10.000')
        ->assertJsonPath('data.0.isReleased', false)
        ->assertJsonPath('data.0.order.reference', $order->order_number)
        ->assertJsonPath('data.0.location.locationCode', 'A-01')
        ->assertJsonMissingPath('data.0.orderLine.sellingPrice');
});

it('filters by item, order and state', function (): void {
    $get = fn (string $query) => $this->withHeaders($this->headers)->getJson('/api/v1/customer/stock/reservations'.$query)->json('meta.total');

    expect($get("?stockItemId={$this->billy->id}"))->toBe(1)
        ->and($get('?orderId='.CustomerPortalOrders::order('ikea')->id))->toBe(1)
        ->and($get('?state=released'))->toBe(0)
        ->and($get('?orderId='.CustomerPortalOrders::order('qoqa')->id))->toBe(0);
});

it('shows its own reservation and answers 404 for another customer', function (): void {
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/stock/reservations/{$this->reservation->id}")->assertOk();
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/stock/reservations/{$this->qoqa->id}")->assertNotFound();
});

it('hides a reservation whose order line belongs to another customer, even on its own item', function (): void {
    // La garde interne l'interdit : la ligne est posée ici sans elle, pour
    // vérifier que le portail ne se repose pas sur ce seul verrou.
    $crossed = StockReservation::factory()->create([
        'stock_item_id' => $this->billy->id, 'stock_location_id' => $this->a->id,
        'order_line_id' => CustomerPortalStock::line('qoqa')->id, 'quantity' => 1, 'status' => 'active',
    ]);

    $this->withHeaders($this->headers)->getJson("/api/v1/customer/stock/reservations/{$crossed->id}")->assertNotFound();
    $this->withHeaders($this->headers)->getJson('/api/v1/customer/stock/reservations')->assertJsonPath('meta.total', 1);
});

it('offers no way to create or release a reservation', function (string $method, string $uri): void {
    // 405 sur une adresse lue en GET, 404 sur une adresse qui n'existe pas :
    // aucune écriture dans les deux cas.
    $status = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->json($method, '/api/v1/customer/stock'.str_replace('{id}', $this->reservation->id, $uri), ['status' => 'released'])
        ->status();

    expect($status)->toBeIn([404, 405]);

    expect($this->reservation->fresh()->released_at)->toBeNull()
        ->and(StockReservation::count())->toBe(2);
})->with([
    'create' => ['POST', '/reservations'],
    'release' => ['POST', '/reservations/{id}/release'],
    'update' => ['PATCH', '/reservations/{id}'],
    'delete' => ['DELETE', '/reservations/{id}'],
]);

it('requires the reservations permission', function (): void {
    $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))->getJson('/api/v1/customer/stock/reservations')->assertForbidden();
});
