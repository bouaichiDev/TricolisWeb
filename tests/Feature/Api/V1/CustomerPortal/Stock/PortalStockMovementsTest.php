<?php

use App\Modules\Stock\Models\StockMovement;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalStock;

/**
 * Mouvements des articles du client, en lecture seule (§16, §30 « Movements »).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $this->a = CustomerPortalStock::location('A-01');
    $this->billy = CustomerPortalStock::item('ikea', 'ART-001');
    $this->kallax = CustomerPortalStock::item('ikea', 'ART-002');
    $this->old = CustomerPortalStock::receive($this->billy, $this->a, '5', 'reception');
    $this->old->forceFill(['created_at' => now()->subDays(10)])->save();
    $this->recent = CustomerPortalStock::receive($this->kallax, $this->a, '7');
    $this->qoqa = CustomerPortalStock::receive(CustomerPortalStock::item('qoqa', 'QOQA-001'), $this->a, '9');
    $this->headers = CustomerPortal::headers('viewer@ikea.test');
});

it('lists the movements of its items, most recent first, with the recorded type', function (): void {
    $data = $this->withHeaders($this->headers)->getJson('/api/v1/customer/stock/movements')->assertOk()->assertJsonPath('meta.total', 2)->json('data');

    expect(collect($data)->pluck('id')->all())->toBe([$this->recent->id, $this->old->id])
        ->and($data[1])->toMatchArray(['movementType' => 'reception', 'direction' => 'entry', 'quantity' => '5.000'])
        ->and($data[1]['destinationLocation']['locationCode'])->toBe('A-01')
        ->and($data[1])->not->toHaveKey('createdBy');
});

it('filters by item, type and dates, and paginates', function (): void {
    $get = fn (string $query) => $this->withHeaders($this->headers)->getJson('/api/v1/customer/stock/movements'.$query)->json('meta.total');

    expect($get("?stockItemId={$this->billy->id}"))->toBe(1)
        ->and($get('?movementType=inbound'))->toBe(1)
        ->and($get('?createdFrom='.now()->subDay()->toDateString()))->toBe(1)
        ->and($get('?createdTo='.now()->subDays(5)->toDateString()))->toBe(1)
        ->and($get("?stockItemId={$this->qoqa->stock_item_id}"))->toBe(0);
});

it('shows its own movement and answers 404 for another customer', function (): void {
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/stock/movements/{$this->recent->id}")->assertOk()->assertJsonPath('data.quantity', '7.000');
    $this->withHeaders($this->headers)->getJson("/api/v1/customer/stock/movements/{$this->qoqa->id}")->assertNotFound();
});

it('offers no way to create, change or delete a movement', function (string $method, string $uri): void {
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->json($method, '/api/v1/customer/stock'.str_replace('{id}', $this->recent->id, $uri), ['stockItemId' => $this->billy->id, 'movementType' => 'inbound', 'quantity' => 100])
        ->assertStatus(405);

    expect(StockMovement::count())->toBe(3);
})->with([
    'create' => ['POST', '/movements'],
    'update' => ['PATCH', '/movements/{id}'],
    'delete' => ['DELETE', '/movements/{id}'],
]);

it('requires the movements permission', function (): void {
    $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))->getJson('/api/v1/customer/stock/movements')->assertForbidden();
});
