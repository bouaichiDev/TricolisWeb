<?php

use App\Modules\Stock\Models\StockBalance;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalStock;

/**
 * Test IDOR de la Phase 5 (§25-§26) : IKEA remplace chaque ULID de l'URL par
 * celui d'une ressource QOQA — toujours 404. IKEA et QOQA partagent pourtant
 * le même emplacement du dépôt.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $shared = CustomerPortalStock::location('A-01');
    $item = CustomerPortalStock::item('qoqa', 'QOQA-001');
    $this->qoqa = [
        'item' => $item->id,
        'movement' => CustomerPortalStock::receive($item, $shared, '30')->id,
        'reservation' => CustomerPortalStock::reserve($item, $shared, CustomerPortalStock::line('qoqa'), '5')->id,
    ];
    $this->balance = StockBalance::where('stock_item_id', $item->id)->firstOrFail();
});

it('answers 404 on every stock endpoint aimed at a QOQA resource', function (string $method, string $uri): void {
    $uri = strtr($uri, ['{item}' => $this->qoqa['item'], '{movement}' => $this->qoqa['movement'], '{reservation}' => $this->qoqa['reservation']]);

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->json($method, "/api/v1/customer/stock{$uri}", ['description' => 'x'])->assertNotFound();
})->with([
    'item' => ['GET', '/items/{item}'],
    'item update' => ['PATCH', '/items/{item}'],
    'balances' => ['GET', '/items/{item}/balances'],
    'movement' => ['GET', '/movements/{movement}'],
    'reservation' => ['GET', '/reservations/{reservation}'],
]);

it('never counts QOQA stock in the IKEA summary or lists', function (): void {
    $headers = CustomerPortal::headers('admin@ikea.test');

    $this->withHeaders($headers)->getJson('/api/v1/customer/stock/summary')->assertJsonPath('data.itemsCount', 0)->assertJsonPath('data.quantity', '0.000');
    $this->withHeaders($headers)->getJson('/api/v1/customer/stock/movements')->assertJsonPath('meta.total', 0);
    $this->withHeaders($headers)->getJson('/api/v1/customer/stock/reservations')->assertJsonPath('meta.total', 0);
    $this->withHeaders($headers)->getJson("/api/v1/customer/stock/items?stockItemId={$this->qoqa['item']}")->assertJsonPath('meta.total', 0);

    expect($this->balance->fresh()->quantity)->toBe('30.000');
});
