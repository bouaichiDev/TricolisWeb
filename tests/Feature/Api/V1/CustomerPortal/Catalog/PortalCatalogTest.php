<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\PortalRoutes;

/**
 * Le catalogue du portail (permissions de la Phase 1, livré en Phase 8) :
 * consulter ses catalogues et articles, en ajouter — jamais ceux d'un autre
 * client, jamais sans le module ouvert.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->ikea = CustomerPortal::customer('ikea');
    $this->ikeaCatalog = CustomerCatalog::where('customer_id', $this->ikea->id)->firstOrFail();

    $qoqa = CustomerPortal::customer('qoqa');
    $qoqa->update(['catalog_enabled' => true]);
    $this->qoqaCatalog = CustomerCatalog::factory()->forCustomer($qoqa)->create(['code' => 'QOQA-MAIN', 'name' => 'Catalogue QOQA']);
    $this->qoqaItem = CustomerCatalogItem::factory()->forCatalog($this->qoqaCatalog)->create(['article_code' => 'QOQA-SECRET', 'name' => 'Article QOQA']);

    $this->admin = CustomerPortal::headers('admin@ikea.test');
    $this->payload = fn (array $overrides = []): array => [
        'catalogId' => $this->ikeaCatalog->id, 'articleCode' => 'NEW-001', 'name' => 'Commode Hemnes', 'weight' => '12.5', ...$overrides,
    ];
});

it('liste les catalogues et les articles du seul client courant', function (): void {
    $catalogs = $this->withHeaders($this->admin)->getJson('/api/v1/customer/catalog/catalogs')->assertOk();
    $items = $this->withHeaders($this->admin)->getJson('/api/v1/customer/catalog/items?perPage=100')->assertOk();

    expect(collect($catalogs->json('data'))->pluck('id')->all())->toBe([$this->ikeaCatalog->id])
        ->and($items->json('meta.total'))->toBeGreaterThan(0)
        ->and($items->getContent())->not->toContain('QOQA-SECRET')
        ->and(collect($items->json('data'))->pluck('catalog.id')->unique()->all())->toBe([$this->ikeaCatalog->id]);
});

it('cherche par code, code-barres ou désignation', function (): void {
    $article = CustomerCatalogItem::where('catalog_id', $this->ikeaCatalog->id)->firstOrFail();

    $this->withHeaders($this->admin)->getJson('/api/v1/customer/catalog/items?search='.urlencode($article->article_code))
        ->assertOk()
        ->assertJsonPath('data.0.articleCode', $article->article_code);
});

it('montre la fiche d’un article avec son usage, et 404 pour l’article d’un autre client', function (): void {
    $article = CustomerCatalogItem::where('catalog_id', $this->ikeaCatalog->id)->firstOrFail();

    $this->withHeaders($this->admin)->getJson("/api/v1/customer/catalog/items/{$article->id}")
        ->assertOk()
        ->assertJsonPath('data.articleCode', $article->article_code)
        ->assertJsonStructure(['data' => ['usage' => ['orderLines', 'stockItems']]]);

    $foreign = $this->withHeaders($this->admin)->getJson("/api/v1/customer/catalog/items/{$this->qoqaItem->id}");
    $nowhere = $this->withHeaders($this->admin)->getJson('/api/v1/customer/catalog/items/'.PortalRoutes::NOWHERE);

    expect($foreign->status())->toBe(404)->and($nowhere->status())->toBe(404);
});

it('ajoute un article actif au catalogue, audité', function (): void {
    $response = $this->withHeaders($this->admin)->postJson('/api/v1/customer/catalog/items', ($this->payload)())
        ->assertCreated()
        ->assertJsonPath('data.articleCode', 'NEW-001')
        ->assertJsonPath('data.isActive', true);

    $this->assertDatabaseHas('customer_catalog_items', ['id' => $response->json('data.id'), 'catalog_id' => $this->ikeaCatalog->id]);
    expect(AuditLog::where('action', 'customer_portal.catalog_item_created')->where('entity_id', $response->json('data.id'))->exists())->toBeTrue();
});

it('accepte un article sans poids ni volume : la colonne prend son défaut', function (): void {
    $this->withHeaders($this->admin)->postJson('/api/v1/customer/catalog/items', ($this->payload)(['weight' => null, 'volume' => null]))
        ->assertCreated()
        ->assertJsonPath('data.weight', '0.000');
});

it('refuse un code déjà présent, un statut choisi, ou le catalogue d’un autre client', function (array $overrides, string $field): void {
    $existing = CustomerCatalogItem::where('catalog_id', $this->ikeaCatalog->id)->value('article_code');
    $overrides = array_map(fn (mixed $value): mixed => match ($value) {
        'EXISTING' => $existing,
        'QOQA' => $this->qoqaCatalog->id,
        default => $value,
    }, $overrides);
    $count = CustomerCatalogItem::count();

    $this->withHeaders($this->admin)->postJson('/api/v1/customer/catalog/items', ($this->payload)($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(CustomerCatalogItem::count())->toBe($count);
})->with([
    'code en double' => [['articleCode' => 'EXISTING'], 'articleCode'],
    'statut' => [['status' => 'inactive'], 'status'],
    'client' => [['customerId' => '01JZZZZZZZZZZZZZZZZZZZZZZZ'], 'customerId'],
    'catalogue étranger' => [['catalogId' => 'QOQA'], 'catalogId'],
]);

it('respecte les rôles : le lecteur consulte, le gestionnaire de stock ajoute', function (): void {
    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson('/api/v1/customer/catalog/items')->assertOk();
    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->postJson('/api/v1/customer/catalog/items', ($this->payload)())->assertForbidden();
});

it('ferme le catalogue quand le module n’est pas ouvert au client', function (): void {
    $this->ikea->update(['catalog_enabled' => false]);

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson('/api/v1/customer/catalog/items')->assertForbidden();
});
