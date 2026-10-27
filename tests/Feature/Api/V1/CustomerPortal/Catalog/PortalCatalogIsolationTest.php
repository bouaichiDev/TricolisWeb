<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Customers\Models\Customer;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\PortalRoutes;

/**
 * Isolation de l'écriture du catalogue (exigence « 100 % ») : IKEA ne modifie,
 * n'active ni ne désactive jamais l'article d'un autre client — même
 * organisation (GETAZ, QOQA) ou autre organisation — et ne peut pas déplacer
 * le sien vers le catalogue d'un autre. Rien ne bouge en base, rien n'est
 * audité, et la réponse est celle d'un identifiant inexistant.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $ikea = CustomerPortal::customer('ikea');
    $this->ikeaItem = CustomerCatalogItem::whereHas('catalog', fn ($q) => $q->where('customer_id', $ikea->id))->firstOrFail();

    $getaz = Customer::factory()->create(['organization_id' => $ikea->organization_id, 'code' => 'getaz', 'catalog_enabled' => true]);
    $this->getazCatalog = CustomerCatalog::factory()->forCustomer($getaz)->create(['code' => 'GETAZ-MAIN']);
    $qoqaCatalog = CustomerCatalog::factory()->forCustomer(CustomerPortal::customer('qoqa'))->create(['code' => 'QOQA-MAIN']);
    $elsewhere = CustomerCatalog::factory()->forCustomer(Customer::factory()->create(['catalog_enabled' => true]))->create();

    $this->foreign = [
        'GETAZ' => CustomerCatalogItem::factory()->forCatalog($this->getazCatalog)->create(['article_code' => 'GZ-1', 'name' => 'Article Getaz']),
        'QOQA' => CustomerCatalogItem::factory()->forCatalog($qoqaCatalog)->create(['article_code' => 'QQ-1', 'name' => 'Article Qoqa']),
        'autre organisation' => CustomerCatalogItem::factory()->forCatalog($elsewhere)->create(['article_code' => 'EX-1', 'name' => 'Article ailleurs']),
    ];
    $this->headers = CustomerPortal::headers('admin@ikea.test');
    $this->snapshot = fn (CustomerCatalogItem $item): array => CustomerCatalogItem::findOrFail($item->id)
        ->only(['catalog_id', 'article_code', 'name', 'status', 'weight', 'updated_at']);
    $this->call = fn (string $id, string $operation) => match ($operation) {
        'modifier' => $this->withHeaders($this->headers)->patchJson("/api/v1/customer/catalog/items/{$id}", ['name' => 'Piraté', 'weight' => 99]),
        'désactiver' => $this->withHeaders($this->headers)->postJson("/api/v1/customer/catalog/items/{$id}/deactivate"),
        'activer' => $this->withHeaders($this->headers)->postJson("/api/v1/customer/catalog/items/{$id}/activate"),
    };
});

it('refuse de toucher l’article d’un autre client, sans rien changer ni auditer', function (string $owner, string $operation): void {
    $target = $this->foreign[$owner];
    $before = ($this->snapshot)($target);
    $audits = AuditLog::count();

    $foreign = ($this->call)($target->id, $operation);
    $nowhere = ($this->call)(PortalRoutes::NOWHERE, $operation);

    expect($foreign->status())->toBe(404)
        ->and($foreign->getContent())->toBe($nowhere->getContent())
        ->and(($this->snapshot)($target))->toEqual($before)
        ->and(AuditLog::count())->toBe($audits);
})->with(['GETAZ', 'QOQA', 'autre organisation'])->with(['modifier', 'désactiver', 'activer']);

it('ne laisse pas déplacer son article vers le catalogue d’un autre client', function (string $field): void {
    $before = ($this->snapshot)($this->ikeaItem);
    $value = $field === 'catalogId' ? $this->getazCatalog->id : $this->getazCatalog->customer_id;

    $this->withHeaders($this->headers)->patchJson("/api/v1/customer/catalog/items/{$this->ikeaItem->id}", [$field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(($this->snapshot)($this->ikeaItem))->toEqual($before);
})->with(['catalogId', 'customerId', 'organizationId']);

it('accepte un code déjà pris chez un autre client : l’unicité est par catalogue', function (): void {
    $this->withHeaders($this->headers)->patchJson("/api/v1/customer/catalog/items/{$this->ikeaItem->id}", ['articleCode' => 'GZ-1'])
        ->assertOk()
        ->assertJsonPath('data.articleCode', 'GZ-1');

    expect(($this->snapshot)($this->foreign['GETAZ'])['article_code'])->toBe('GZ-1')
        ->and(CustomerCatalogItem::findOrFail($this->foreign['GETAZ']->id)->catalog_id)->toBe($this->getazCatalog->id);
});
