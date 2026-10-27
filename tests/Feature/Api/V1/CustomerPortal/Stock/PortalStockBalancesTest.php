<?php

use App\Modules\Stock\Models\StockBalance;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalStock;

/**
 * Répartition par emplacement, en lecture seule (§11, §15, §26, §30 « Balances »).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $this->a = CustomerPortalStock::location('A-01');
    $this->b = CustomerPortalStock::location('B-02');
    $this->billy = CustomerPortalStock::item('ikea', 'ART-001');
    $this->qoqa = CustomerPortalStock::item('qoqa', 'QOQA-001', ['description' => 'Secret QOQA']);
    CustomerPortalStock::receive($this->billy, $this->a, '50');
    CustomerPortalStock::receive($this->billy, $this->b, '70');
    CustomerPortalStock::receive($this->qoqa, $this->a, '999');
    CustomerPortalStock::reserve($this->billy, $this->a, CustomerPortalStock::line('ikea'), '10');
    CustomerPortalStock::reserve($this->billy, $this->b, CustomerPortalStock::line('ikea'), '10');
    $this->headers = CustomerPortal::headers('viewer@ikea.test');
});

it('shows each location of its item with quantity, reserved and available', function (): void {
    $balances = collect($this->withHeaders($this->headers)->getJson("/api/v1/customer/stock/items/{$this->billy->id}/balances")->assertOk()->json('data'))
        ->keyBy('location.locationCode');

    expect($balances['A-01'])->toMatchArray(['quantity' => '50.000', 'reservedQuantity' => '10.000', 'availableQuantity' => '40.000'])
        ->and($balances['B-02'])->toMatchArray(['quantity' => '70.000', 'reservedQuantity' => '10.000', 'availableQuantity' => '60.000'])
        ->and($balances['A-01']['location'])->toHaveKeys(['depot', 'zoneCode', 'aisle', 'rack', 'level', 'locationCode'])
        ->and($balances['A-01']['location'])->not->toHaveKeys(['barcode', 'legacyId', 'status']);
});

it('never shows what another customer holds at the same location', function (): void {
    $body = json_encode($this->withHeaders($this->headers)->getJson("/api/v1/customer/stock/items/{$this->billy->id}/balances")->json(), JSON_UNESCAPED_UNICODE);

    expect($body)->not->toContain('999')->not->toContain('Secret QOQA')->not->toContain($this->qoqa->id);
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/stock/items/{$this->qoqa->id}/balances")->assertNotFound();
});

it('offers no way to write a balance', function (string $method): void {
    $balance = StockBalance::where('stock_item_id', $this->billy->id)->firstOrFail();

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->json($method, "/api/v1/customer/stock/items/{$this->billy->id}/balances", ['quantity' => 0])
        ->assertStatus(405);
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->json($method, "/api/v1/customer/stock/balances/{$balance->id}", ['quantity' => 0])
        ->assertNotFound();

    expect($balance->fresh()->quantity)->toBe('50.000');
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);
