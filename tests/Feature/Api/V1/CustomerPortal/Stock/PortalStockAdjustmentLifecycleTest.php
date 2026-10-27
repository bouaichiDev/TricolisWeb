<?php

use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockMovement;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalStock;
use Tests\Support\StockAdjustments;

/**
 * Le cycle complet d'une demande de correction : le client la dépose depuis
 * le portail — le stock ne bouge pas —, l'interne la tranche, et seule la
 * validation corrige le stock, par un mouvement `CreateStockMovementAction`.
 * Le portail n'a aucune route pour décider ni pour écrire une quantité.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $this->location = CustomerPortalStock::location('A-01');
    $this->item = CustomerPortalStock::item('ikea', 'ART-CYCLE');
    CustomerPortalStock::receive($this->item, $this->location, '50');
    $this->headers = CustomerPortal::headers('admin@ikea.test');
    $this->submit = fn (string $quantity) => $this->withHeaders($this->headers)->postJson('/api/v1/customer/stock/adjustment-requests', [
        'stockItemId' => $this->item->id, 'stockLocationId' => $this->location->id, 'requestedQuantity' => $quantity, 'reason' => 'Inventaire',
    ]);
    $this->portalView = fn (string $id) => $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson("/api/v1/customer/stock/adjustment-requests/{$id}");
});

it('validée par l’interne : le stock rejoint la quantité constatée par un mouvement Stock, et le client le voit', function (): void {
    $id = ($this->submit)('47')->assertCreated()->assertJsonPath('data.status.code', 'pending')->json('data.id');

    // Déposée : rien n'a bougé.
    expect(StockAdjustments::balance($this->item, $this->location))->toBe('50.000')
        ->and(StockMovement::where('stock_item_id', $this->item->id)->count())->toBe(1);

    StockAdjustments::approve(StockAdjustmentRequest::findOrFail($id), ['reviewComment' => 'Recompté.'])->assertOk();

    $movement = StockMovement::where('source_entity_id', $id)->sole();
    expect(StockAdjustments::balance($this->item, $this->location))->toBe('47.000')
        ->and($movement->source_entity_type)->toBe('stock_adjustment_request')
        ->and((string) $movement->quantity)->toBe('3.000')
        ->and($movement->source_location_id)->toBe($this->location->id);

    ($this->portalView)($id)->assertOk()
        ->assertJsonPath('data.status.code', 'approved')
        ->assertJsonPath('data.reviewComment', 'Recompté.');
});

it('refusée par l’interne : aucun mouvement, stock inchangé, motif visible du client', function (): void {
    $id = ($this->submit)('10')->assertCreated()->json('data.id');

    StockAdjustments::reject(StockAdjustmentRequest::findOrFail($id), ['reviewComment' => 'Le stock est juste.'])->assertOk();

    expect(StockAdjustments::balance($this->item, $this->location))->toBe('50.000')
        ->and(StockMovement::where('source_entity_id', $id)->exists())->toBeFalse();
    ($this->portalView)($id)->assertOk()
        ->assertJsonPath('data.status.code', 'rejected')
        ->assertJsonPath('data.reviewComment', 'Le stock est juste.');
});

it('ne donne au portail aucun moyen de trancher ni d’écrire une quantité', function (): void {
    $id = ($this->submit)('5')->assertCreated()->json('data.id');

    foreach (['approve', 'reject'] as $decision) {
        expect($this->withHeaders($this->headers)->postJson("/api/v1/customer/stock/adjustment-requests/{$id}/{$decision}")->status())->toBeIn([404, 405]);
    }
    // Le jeton du portail n'ouvre pas les routes internes de décision.
    $this->withHeaders($this->headers)->postJson("/api/v1/stock-adjustment-requests/{$id}/approve")->assertForbidden();
    $this->withHeaders($this->headers)->patchJson("/api/v1/customer/stock/items/{$this->item->id}", ['quantity' => 999])->assertUnprocessable();

    expect(StockAdjustmentRequest::findOrFail($id)->isPending())->toBeTrue()
        ->and(StockAdjustments::balance($this->item, $this->location))->toBe('50.000');
});
