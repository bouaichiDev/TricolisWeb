<?php

use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalStock;

/**
 * Liste et résumé du stock du portail (§9-§11, §30 « Items »).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $this->a = CustomerPortalStock::location('A-01');
    $this->b = CustomerPortalStock::location('B-02');
    $this->billy = CustomerPortalStock::item('ikea', 'ART-001', ['description' => 'Bibliothèque Billy', 'barcode' => '7612345678901']);
    $this->kallax = CustomerPortalStock::item('ikea', 'ART-002', ['description' => 'Étagère Kallax', 'status' => 'archived']);
    $this->qoqa = CustomerPortalStock::item('qoqa', 'QOQA-001');
    CustomerPortalStock::receive($this->billy, $this->a, '50.500');
    CustomerPortalStock::receive($this->billy, $this->b, '70');
    CustomerPortalStock::receive($this->qoqa, $this->a, '999');
    CustomerPortalStock::reserve($this->billy, $this->a, CustomerPortalStock::line('ikea'), '20');
});

function stockGet(string $uri, string $email = 'viewer@ikea.test'): TestResponse
{
    return test()->withHeaders(CustomerPortal::headers($email))->getJson("/api/v1/customer/stock{$uri}");
}

it('summarizes the stock of the current customer only, in decimal strings', function (): void {
    stockGet('/summary')->assertOk()->assertExactJson(['data' => [
        'itemsCount' => 2, 'quantity' => '120.500', 'reservedQuantity' => '20.000', 'availableQuantity' => '100.500',
    ], 'meta' => []]);
});

it('lists only its own items, with totals aggregated over every location', function (): void {
    $response = stockGet('/items')->assertOk()->assertJsonPath('meta.total', 2);
    $billy = collect($response->json('data'))->firstWhere('id', $this->billy->id);

    expect(collect($response->json('data'))->pluck('id'))->not->toContain($this->qoqa->id)
        ->and($billy['stock'])->toBe(['quantity' => '120.500', 'reservedQuantity' => '20.000', 'availableQuantity' => '100.500'])
        ->and($billy['actions'])->toMatchArray(['canView' => true, 'canViewMovements' => true, 'canViewReservations' => true, 'canRequestChange' => false])
        ->and($billy['status'])->toHaveKeys(['code', 'label']);
});

it('shows zero for an item without any balance', function (): void {
    expect(collect(stockGet('/items')->json('data'))->firstWhere('id', $this->kallax->id)['stock'])
        ->toBe(['quantity' => '0.000', 'reservedQuantity' => '0.000', 'availableQuantity' => '0.000']);
});

it('searches, filters, sorts and paginates on the backend', function (): void {
    expect(stockGet('/items?search=Kallax')->json('data.0.id'))->toBe($this->kallax->id)
        ->and(stockGet('/items?search=7612345678901')->json('data.0.id'))->toBe($this->billy->id)
        ->and(stockGet('/items?status[]=archived')->json('meta.total'))->toBe(1)
        ->and(stockGet('/items?articleCode=ART-001')->json('meta.total'))->toBe(1)
        ->and(stockGet("/items?locationId={$this->b->id}")->json('data.0.id'))->toBe($this->billy->id)
        ->and(stockGet("/items?locationId={$this->b->id}")->json('meta.total'))->toBe(1)
        ->and(stockGet('/items?sort=quantity&direction=desc')->json('data.0.id'))->toBe($this->billy->id)
        ->and(stockGet('/items?perPage=1')->json('meta.lastPage'))->toBe(2);

    stockGet('/items?perPage=500')->assertUnprocessable();
    stockGet('/items?customerId='.$this->qoqa->customer_id)->assertUnprocessable()->assertJsonValidationErrors('customerId');
});

it('offers as location filter only the locations where the customer has stock', function (): void {
    $other = CustomerPortalStock::location('C-99');

    $ids = collect(stockGet('/items')->json('meta.filters.locations'))->pluck('id');

    expect($ids)->toContain($this->a->id, $this->b->id)->not->toContain($other->id);
});

it('shows its own item and answers 404 for any other', function (): void {
    stockGet("/items/{$this->billy->id}")->assertOk()->assertJsonPath('data.stock.quantity', '120.500');
    stockGet("/items/{$this->qoqa->id}")->assertNotFound();
    stockGet('/items/pas-un-ulid')->assertNotFound();
});

it('requires the stock permission, and the module opened by the carrier', function (): void {
    stockGet('/items', 'orders@ikea.test')->assertForbidden();

    CustomerPortal::customer('ikea')->update(['stock_enabled' => false]);
    stockGet('/summary')->assertForbidden();
});
