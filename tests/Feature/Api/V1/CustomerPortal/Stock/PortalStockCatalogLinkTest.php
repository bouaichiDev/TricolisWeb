<?php

use App\Modules\Catalogs\Models\CustomerCatalogItem;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;
use Tests\Support\CustomerPortalStock;

/**
 * Un article de catalogue désactivé n'est plus proposé (lot 1) : le portail
 * refuse aussi de le rattacher, par l'API, à un article de stock — sans
 * casser le rattachement d'un article désactivé après coup.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $this->catalogItem = CustomerCatalogItem::findOrFail(CustomerPortalOrders::article('ikea'));
    $this->headers = CustomerPortal::headers('admin@ikea.test');
});

it('refuse de rattacher un article de catalogue désactivé à la création', function (): void {
    $this->catalogItem->update(['status' => 'inactive']);

    $this->withHeaders($this->headers)->postJson('/api/v1/customer/stock/items', ['catalogItemId' => $this->catalogItem->id, 'articleCode' => 'ART-OFF', 'status' => 'active'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('catalogItemId');
});

it('refuse de rattacher un article de catalogue désactivé à la modification', function (): void {
    $item = CustomerPortalStock::item('ikea', 'ART-RELINK');
    $this->catalogItem->update(['status' => 'inactive']);

    $this->withHeaders($this->headers)->patchJson("/api/v1/customer/stock/items/{$item->id}", ['catalogItemId' => $this->catalogItem->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('catalogItemId');
});

it('garde le rattachement d’un article désactivé depuis', function (): void {
    $id = $this->withHeaders($this->headers)->postJson('/api/v1/customer/stock/items', ['catalogItemId' => $this->catalogItem->id, 'articleCode' => 'ART-KEEP', 'status' => 'active'])
        ->assertCreated()->json('data.id');
    $this->catalogItem->update(['status' => 'inactive']);

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->patchJson("/api/v1/customer/stock/items/{$id}", ['catalogItemId' => $this->catalogItem->id, 'description' => 'Toujours lié'])
        ->assertOk()
        ->assertJsonPath('data.catalogItem.id', $this->catalogItem->id);
});
