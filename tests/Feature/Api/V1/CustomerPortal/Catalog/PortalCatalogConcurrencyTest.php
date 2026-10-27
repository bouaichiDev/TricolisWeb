<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CustomerPortal;

/**
 * Deux écritures simultanées du même code article : la seconde passe la
 * vérification avant que la première n'écrive, et c'est l'index unique
 * `(catalog_id, article_code)` qui la refuse. Le client reçoit l'erreur
 * métier du champ (422), jamais une erreur 500.
 *
 * La course est rendue déterministe : l'article concurrent est inséré juste
 * avant l'écriture, une fois la vérification passée. Et une modification qui
 * ne change rien n'écrit ni la ligne ni l'audit.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->item = CustomerCatalogItem::whereHas('catalog', fn ($q) => $q->where('customer_id', CustomerPortal::customer('ikea')->id))->orderBy('article_code')->firstOrFail();
    $this->admin = CustomerPortal::headers('admin@ikea.test');
    $this->competitor = function (string $event): void {
        CustomerCatalogItem::{$event}(function (): void {
            DB::table('customer_catalog_items')->insertOrIgnore([
                'id' => (string) Str::ulid(), 'catalog_id' => $this->item->catalog_id, 'article_code' => 'RACE-1', 'name' => 'Concurrent',
                'weight' => 0, 'volume' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    };
});

it('répond 422 sur le code quand un renommage concurrent l’a pris entre-temps', function (): void {
    ($this->competitor)('updating');
    $audits = AuditLog::count();

    $this->withHeaders($this->admin)->patchJson("/api/v1/customer/catalog/items/{$this->item->id}", ['articleCode' => 'RACE-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('articleCode');

    expect($this->item->fresh()->article_code)->not->toBe('RACE-1')
        ->and(AuditLog::count())->toBe($audits);
});

it('répond 422 sur le code quand un ajout concurrent l’a pris entre-temps', function (): void {
    ($this->competitor)('creating');

    $this->withHeaders($this->admin)->postJson('/api/v1/customer/catalog/items', ['catalogId' => $this->item->catalog_id, 'articleCode' => 'RACE-1', 'name' => 'Mon article'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('articleCode');
});

it('n’écrit aucun audit pour une modification qui ne change rien', function (bool $sameValues): void {
    $audits = AuditLog::count();
    $before = $this->item->fresh()->updated_at;
    $payload = $sameValues ? ['name' => $this->item->name, 'articleCode' => $this->item->article_code, 'weight' => (string) $this->item->weight] : [];

    $this->withHeaders($this->admin)->patchJson("/api/v1/customer/catalog/items/{$this->item->id}", $payload)->assertOk();

    expect(AuditLog::count())->toBe($audits)
        ->and($this->item->fresh()->updated_at->equalTo($before))->toBeTrue();
})->with(['corps vide' => [false], 'mêmes valeurs' => [true]]);
