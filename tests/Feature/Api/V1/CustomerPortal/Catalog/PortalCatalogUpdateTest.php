<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;

/**
 * Modifier, désactiver et réactiver un article depuis le portail (lot 1) :
 * mêmes règles que le back-office, statut jamais choisi par la modification,
 * article désactivé retiré du formulaire de commande.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->ikea = CustomerPortal::customer('ikea');
    $this->items = CustomerCatalogItem::whereHas('catalog', fn ($q) => $q->where('customer_id', $this->ikea->id))->orderBy('article_code')->get();
    $this->item = $this->items->first();
    $this->admin = CustomerPortal::headers('admin@ikea.test');
    $this->url = "/api/v1/customer/catalog/items/{$this->item->id}";
});

it('modifie un article et l’audite deux fois', function (): void {
    $this->withHeaders($this->admin)->patchJson($this->url, ['name' => 'Bibliothèque Billy blanche', 'weight' => '31.25', 'articleCode' => 'BILLY-80-W'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Bibliothèque Billy blanche')
        ->assertJsonPath('data.articleCode', 'BILLY-80-W')
        ->assertJsonPath('data.actions.canUpdate', true);

    expect($this->item->fresh()->weight)->toBe('31.250')
        ->and(AuditLog::where('action', 'updated')->where('entity_id', $this->item->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'customer_portal.catalog_item_updated')->where('entity_id', $this->item->id)->exists())->toBeTrue();
});

it('remet à zéro un poids ou un volume vidé, au lieu de l’ignorer', function (): void {
    $this->withHeaders($this->admin)->patchJson($this->url, ['weight' => null, 'volume' => null])
        ->assertOk()
        ->assertJsonPath('data.weight', '0.000')
        ->assertJsonPath('data.volume', '0.0000');
});

it('refuse une mesure trop grande pour la colonne, au lieu d’une erreur 500', function (string $field, string $value): void {
    $this->withHeaders($this->admin)->patchJson($this->url, [$field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([['weight', '1000000000'], ['volume', '100000000'], ['length', '1000000000']]);

it('refuse un code déjà présent dans le même catalogue', function (): void {
    $this->withHeaders($this->admin)->patchJson($this->url, ['articleCode' => $this->items->last()->article_code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('articleCode');
});

it('refuse de choisir le statut par la modification', function (): void {
    $this->withHeaders($this->admin)->patchJson($this->url, ['status' => 'inactive'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($this->item->fresh()->status)->toBe('active');
});

it('désactive un article : il reste au catalogue mais quitte le formulaire de commande', function (): void {
    $this->withHeaders($this->admin)->postJson("{$this->url}/deactivate")->assertOk()->assertJsonPath('data.status', 'inactive');

    $search = $this->withHeaders($this->admin)->getJson('/api/v1/customer/catalog-items?search='.urlencode($this->item->article_code))->assertOk();

    expect(collect($search->json('data'))->pluck('id')->all())->not->toContain($this->item->id)
        ->and(AuditLog::where('action', 'customer_portal.catalog_item_deactivated')->where('entity_id', $this->item->id)->exists())->toBeTrue();
    $this->withHeaders($this->admin)->getJson($this->url)->assertOk()->assertJsonPath('data.isActive', false);
});

it('réactive un article désactivé', function (): void {
    $this->item->update(['status' => 'inactive']);

    $this->withHeaders($this->admin)->postJson("{$this->url}/activate")->assertOk()->assertJsonPath('data.isActive', true);

    expect(AuditLog::where('action', 'customer_portal.catalog_item_activated')->exists())->toBeTrue();
});

it('n’écrit rien quand l’article est déjà dans l’état demandé', function (): void {
    $audits = AuditLog::count();

    $this->withHeaders($this->admin)->postJson("{$this->url}/activate")->assertOk()->assertJsonPath('data.isActive', true);

    expect(AuditLog::count())->toBe($audits);
});

it('refuse le lecteur et le gestionnaire de commandes, qui n’ont pas la permission', function (): void {
    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->patchJson($this->url, ['name' => 'X'])->assertForbidden();
    $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))->postJson("{$this->url}/deactivate")->assertForbidden();
    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson($this->url)->assertOk()->assertJsonPath('data.actions.canUpdate', false);

    expect($this->item->fresh()->only(['name', 'status']))->toBe(['name' => $this->item->name, 'status' => 'active']);
});

it('ferme la modification quand le module catalogue n’est pas ouvert', function (): void {
    $this->ikea->update(['catalog_enabled' => false]);

    $this->withHeaders($this->admin)->patchJson($this->url, ['name' => 'X'])->assertForbidden();
});
