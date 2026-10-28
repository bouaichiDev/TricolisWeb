<?php

use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use Database\Seeders\DemoProviderPortalSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ProviderPortal;

/**
 * Décomptes au portail : ceux du fournisseur, clôturés, en lecture seule,
 * avec les montants du module financier — rien d'un autre fournisseur, d'un
 * brouillon ni de la facturation client.
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $this->mine = ProviderSettlement::factory()->forProvider($this->alpha)->create([
        'settlement_number' => 'STL-ALPHA-1', 'status' => 'closed', 'subtotal' => 240, 'tax_total' => 19.44, 'total' => 259.44,
        'period_from' => '2026-10-01', 'period_to' => '2026-10-31',
    ]);
    $service = OrderService::factory()->create(['order_id' => Order::factory()->create(['organization_id' => $this->alpha->organization_id])->id]);
    ProviderSettlementLine::factory()->forSettlement($this->mine)->create(['order_service_id' => $service->id, 'description' => 'Livraison', 'quantity' => 3, 'unit_cost' => 80, 'total_cost' => 240]);
    $this->draft = ProviderSettlement::factory()->forProvider($this->alpha)->create(['status' => 'draft']);
    $this->theirs = ProviderSettlement::factory()->forProvider(ProviderPortal::provider('transports-beta'))->create(['status' => 'closed', 'total' => 999]);
    $this->doc = function (string $name, string $alias, string $entityId): Document {
        Storage::disk('local')->put("documents/{$name}", "contenu {$name}");
        $document = Document::create([
            'organization_id' => $this->alpha->organization_id, 'document_type' => 'settlement', 'status' => 'active',
            'file_name' => "{$name}.pdf", 'storage_path' => "documents/{$name}", 'mime_type' => 'application/pdf', 'size' => 10,
        ]);
        $document->links()->create(['entity_type' => $alias, 'entity_id' => $entityId]);

        return $document;
    };
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
    $this->url = '/api/v1/provider/settlements';
});

it('ne liste que les décomptes clôturés du fournisseur, avec leurs montants exacts', function (): void {
    $rows = $this->withHeaders($this->admin)->getJson($this->url)->assertOk()->json('data');

    expect(collect($rows)->pluck('id')->all())->toBe([$this->mine->id])
        ->and($rows[0])->toMatchArray(['settlementNumber' => 'STL-ALPHA-1', 'status' => 'closed', 'subtotal' => '240.00', 'taxTotal' => '19.44', 'total' => '259.44'])
        ->and(json_encode($rows))->not->toContain('999');
});

it('détaille les lignes par numéro de service, sans commande ni client', function (): void {
    $detail = $this->withHeaders($this->admin)->getJson("{$this->url}/{$this->mine->id}")->assertOk()->json('data');

    expect($detail['lines'])->toHaveCount(1)
        ->and(array_keys($detail['lines'][0]))->toBe(['id', 'serviceNumber', 'description', 'quantity', 'unitCost', 'totalCost'])
        ->and($detail['lines'][0]['totalCost'])->toBe('240.00');
});

it('répond 404 pour un brouillon, un décompte d’un autre fournisseur ou inexistant', function (string $case): void {
    $id = match ($case) {
        'brouillon' => $this->draft->id, 'autre fournisseur' => $this->theirs->id, 'inexistant' => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
    };

    $this->withHeaders($this->admin)->getJson("{$this->url}/{$id}")->assertNotFound();
})->with(['brouillon', 'autre fournisseur', 'inexistant']);

it('télécharge le document du décompte, jamais celui d’un autre ni une facture client', function (): void {
    $own = ($this->doc)('releve', 'provider_settlement', $this->mine->id);
    $foreign = ($this->doc)('releve-beta', 'provider_settlement', $this->theirs->id);
    $invoice = ($this->doc)('facture', 'order', Order::factory()->create(['organization_id' => $this->alpha->organization_id])->id);

    $this->withHeaders($this->admin)->getJson("{$this->url}/{$this->mine->id}/documents")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
    expect($this->withHeaders($this->admin)->get("{$this->url}/{$this->mine->id}/documents/{$own->id}")->assertOk()->streamedContent())->toBe('contenu releve');

    foreach ([$foreign, $invoice] as $document) {
        $this->withHeaders($this->admin)->get("{$this->url}/{$this->mine->id}/documents/{$document->id}")->assertNotFound();
    }
});

it('reste en lecture seule et fermé sans la permission', function (): void {
    foreach (['postJson' => $this->url, 'patchJson' => "{$this->url}/{$this->mine->id}", 'deleteJson' => "{$this->url}/{$this->mine->id}"] as $method => $url) {
        expect($this->withHeaders($this->admin)->{$method}($url)->status())->toBeIn([404, 405]);
    }

    $this->withHeaders(ProviderPortal::headers('viewer@transports-alpha.test'))->getJson($this->url)->assertForbidden();

    Role::where('organization_id', $this->alpha->organization_id)->where('code', ProviderPortalRoles::ADMIN)->firstOrFail()
        ->permissions()->detach(Permission::where('code', 'provider.settlements.documents.view')->value('id'));
    $this->withHeaders($this->admin)->getJson("{$this->url}/{$this->mine->id}/documents")->assertForbidden();
});
