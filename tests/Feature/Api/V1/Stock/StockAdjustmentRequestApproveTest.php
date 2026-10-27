<?php

use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockMovement;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortalStock;
use Tests\Support\StockAdjustments;

/**
 * Accepter une demande de correction (Phase 5b §4) : le stock est ramené à la
 * quantité constatée, par `CreateStockMovementAction` et par elle seule.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $this->a = CustomerPortalStock::location('A-01');
    $this->b = CustomerPortalStock::location('B-02');
    $this->billy = CustomerPortalStock::item('ikea', 'ART-001');
    CustomerPortalStock::receive($this->billy, $this->a, '50');
    CustomerPortalStock::receive($this->billy, $this->b, '70');
});

it('brings the balance up with an inbound movement when the customer counted more', function (): void {
    $request = StockAdjustments::request($this->billy, $this->a, '55');

    $movementId = StockAdjustments::approve($request, ['reviewComment' => 'Cartons retrouvés.'])->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.reviewComment', 'Cartons retrouvés.')
        ->assertJsonPath('data.reviewer.id', authUser()->id)
        ->assertJsonPath('data.stockMovement.quantity', '5.000')
        ->json('data.stockMovement.id');

    $movement = StockMovement::findOrFail($movementId);

    expect(StockAdjustments::balance($this->billy, $this->a))->toBe('55.000')
        ->and($movement->destination_location_id)->toBe($this->a->id)
        ->and($movement->source_location_id)->toBeNull()
        ->and($movement->movement_type)->toBe('adjustment')
        ->and($movement->source_entity_type)->toBe('stock_adjustment_request')
        ->and($movement->source_entity_id)->toBe($request->id)
        ->and($request->fresh()->stock_movement_id)->toBe($movementId);
});

it('brings the balance down with an outbound movement when the customer counted less', function (): void {
    $request = StockAdjustments::request($this->billy, $this->a, '47.5');

    StockAdjustments::approve($request)->assertOk()->assertJsonPath('data.stockMovement.quantity', '2.500');

    $movement = StockMovement::where('source_entity_id', $request->id)->sole();

    expect(StockAdjustments::balance($this->billy, $this->a))->toBe('47.500')
        ->and($movement->source_location_id)->toBe($this->a->id)
        ->and($movement->destination_location_id)->toBeNull();
});

it('corrects towards the counted quantity when the stock moved since the request', function (): void {
    $request = StockAdjustments::request($this->billy, $this->a, '47.5');
    CustomerPortalStock::receive($this->billy, $this->a, '10');

    StockAdjustments::approve($request)->assertOk()->assertJsonPath('data.stockMovement.quantity', '12.500');

    expect(StockAdjustments::balance($this->billy, $this->a))->toBe('47.500');
});

it('approves without any movement when the stock already matches', function (): void {
    $request = StockAdjustments::request($this->billy, $this->a, '47.5');
    StockAdjustments::withdraw($this->billy, $this->a, '2.5');
    $movements = StockMovement::count();

    StockAdjustments::approve($request)->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.stockMovement', null);

    expect(StockMovement::count())->toBe($movements)
        ->and($request->fresh()->stock_movement_id)->toBeNull();
});

it('refuses to go below the reserved quantity, and leaves everything untouched', function (): void {
    CustomerPortalStock::reserve($this->billy, $this->a, CustomerPortalStock::line('ikea'), '45');
    $request = StockAdjustments::request($this->billy, $this->a, '40');
    $movements = StockMovement::count();

    StockAdjustments::approve($request)->assertStatus(409);

    expect($request->fresh()->status->value)->toBe('pending')
        ->and(StockAdjustments::balance($this->billy, $this->a))->toBe('50.000')
        ->and(StockMovement::count())->toBe($movements);
});

it('requires a location for a whole-item request, and corrects the item total there', function (): void {
    $request = StockAdjustments::request($this->billy, null, '118');

    StockAdjustments::approve($request)->assertUnprocessable()->assertJsonValidationErrors('stockLocationId');
    StockAdjustments::approve($request, ['stockLocationId' => CustomerPortalStock::location('C-99')->id])
        ->assertUnprocessable()->assertJsonValidationErrors('stockLocationId');

    StockAdjustments::approve($request, ['stockLocationId' => $this->b->id])->assertOk()
        ->assertJsonPath('data.stockLocationId', $this->b->id)
        ->assertJsonPath('data.stockMovement.quantity', '2.000');

    expect(StockAdjustments::balance($this->billy, $this->b))->toBe('68.000')
        ->and(StockAdjustments::balance($this->billy, $this->a))->toBe('50.000');
});

it('refuses to choose a location for a request that already names one', function (): void {
    $request = StockAdjustments::request($this->billy, $this->a, '47.5');

    StockAdjustments::approve($request, ['stockLocationId' => $this->b->id])
        ->assertUnprocessable()->assertJsonValidationErrors('stockLocationId');
});

it('never reviews a request twice', function (): void {
    $request = StockAdjustments::request($this->billy, $this->a, '47.5');
    StockAdjustments::approve($request)->assertOk();

    StockAdjustments::approve($request)->assertStatus(409);
    StockAdjustments::reject($request)->assertStatus(409);

    expect(StockMovement::where('source_entity_id', $request->id)->count())->toBe(1)
        ->and(StockAdjustments::balance($this->billy, $this->a))->toBe('47.500')
        ->and(StockAdjustmentRequest::findOrFail($request->id)->status->value)->toBe('approved');
});

it('uses the movement type set on the organization', function (): void {
    authOrganization()->update(['settings' => ['stock' => ['adjustmentMovementType' => 'inventory_fix']]]);
    $request = StockAdjustments::request($this->billy, $this->a, '47.5');

    StockAdjustments::approve($request)->assertOk()->assertJsonPath('data.stockMovement.movementType', 'inventory_fix');
});
