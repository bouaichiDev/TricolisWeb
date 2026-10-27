<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockMovement;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalStock;

/**
 * Ajout et correction d'un article en stock (§13-§14, §30 « Création »).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $this->catalogItem = CustomerPortalOrders::article('ikea');
});

function stockWrite(string $method, string $uri, array $payload, string $email = 'admin@ikea.test'): TestResponse
{
    return test()->withHeaders(CustomerPortal::headers($email))->json($method, "/api/v1/customer/stock{$uri}", $payload);
}

it('creates an item for the current customer, linked to its catalog, without any stock', function (): void {
    $id = stockWrite('POST', '/items', ['catalogItemId' => $this->catalogItem, 'articleCode' => 'ART-NEW', 'barcode' => '761000', 'description' => 'Nouveau', 'status' => 'active'])
        ->assertCreated()
        ->assertJsonPath('data.catalogItem.id', $this->catalogItem)
        ->assertJsonPath('data.stock', ['quantity' => '0.000', 'reservedQuantity' => '0.000', 'availableQuantity' => '0.000'])
        ->json('data.id');

    expect(StockItem::findOrFail($id)->customer_id)->toBe(CustomerPortal::customer('ikea')->id)
        ->and(StockBalance::where('stock_item_id', $id)->exists())->toBeFalse()
        ->and(StockMovement::where('stock_item_id', $id)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'customer_portal.stock_item_created')->where('entity_id', $id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'stock_item.created')->where('entity_id', $id)->exists())->toBeTrue();
});

it('refuses the catalog item of another customer', function (): void {
    $qoqaCatalog = CustomerCatalog::create(['customer_id' => CustomerPortal::customer('qoqa')->id, 'code' => 'q', 'name' => 'QOQA', 'status' => 'active']);
    $qoqaItem = $qoqaCatalog->items()->create(['article_code' => 'Q-1', 'name' => 'Table', 'status' => 'active']);

    stockWrite('POST', '/items', ['catalogItemId' => $qoqaItem->id, 'articleCode' => 'ART-Q', 'status' => 'active'])
        ->assertUnprocessable()->assertJsonValidationErrors('catalogItemId');
});

it('refuses any quantity, location or customer in the payload', function (string $field, mixed $value): void {
    stockWrite('POST', '/items', ['articleCode' => 'ART-X', 'status' => 'active', $field => $value])
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(StockItem::where('article_code', 'ART-X')->exists())->toBeFalse();
})->with([
    'quantity' => ['quantity', 10], 'reservedQuantity' => ['reservedQuantity', 1], 'availableQuantity' => ['availableQuantity', 9],
    'stockLocationId' => ['stockLocationId', '01J0000000000000000000000X'], 'depotId' => ['depotId', '01J0000000000000000000000X'],
    'customerId' => ['customerId', '01J0000000000000000000000X'],
]);

it('validates the article code, unique per customer, and the referential status', function (): void {
    CustomerPortalStock::item('ikea', 'ART-DUP');
    CustomerPortalStock::item('qoqa', 'ART-QOQA');

    stockWrite('POST', '/items', ['articleCode' => 'ART-DUP', 'status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('articleCode');
    stockWrite('POST', '/items', ['articleCode' => 'ART-QOQA', 'status' => 'active'])->assertCreated();
    stockWrite('POST', '/items', ['articleCode' => 'ART-Y', 'status' => 'inventé'])->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('corrects the metadata of its item, never its quantities', function (): void {
    $item = CustomerPortalStock::item('ikea', 'ART-META');
    CustomerPortalStock::receive($item, CustomerPortalStock::location('A-01'), '12');

    stockWrite('PATCH', "/items/{$item->id}", ['description' => 'Corrigée', 'status' => 'archived'])->assertOk()
        ->assertJsonPath('data.description', 'Corrigée')->assertJsonPath('data.stock.quantity', '12.000');

    stockWrite('PATCH', "/items/{$item->id}", ['status' => null])->assertUnprocessable()->assertJsonValidationErrors('status');

    foreach (['quantity', 'reservedQuantity', 'availableQuantity', 'stockLocationId'] as $field) {
        stockWrite('PATCH', "/items/{$item->id}", [$field => 0])->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    expect(StockBalance::where('stock_item_id', $item->id)->value('quantity'))->toBe('12.000')
        ->and(AuditLog::where('action', 'customer_portal.stock_item_updated')->exists())->toBeTrue();
});

it('answers 404 when correcting the item of another customer', function (): void {
    $qoqa = CustomerPortalStock::item('qoqa', 'QOQA-1', ['description' => 'QOQA']);

    stockWrite('PATCH', "/items/{$qoqa->id}", ['description' => 'Piraté'])->assertNotFound();
    expect($qoqa->fresh()->description)->toBe('QOQA');
});

it('requires the create and update permissions', function (): void {
    $item = CustomerPortalStock::item('ikea', 'ART-PERM');

    stockWrite('POST', '/items', ['articleCode' => 'ART-V', 'status' => 'active'], 'viewer@ikea.test')->assertForbidden();
    stockWrite('PATCH', "/items/{$item->id}", ['description' => 'x'], 'viewer@ikea.test')->assertForbidden();
});
