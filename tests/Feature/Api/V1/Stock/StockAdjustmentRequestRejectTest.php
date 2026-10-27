<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Stock\Models\StockMovement;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalStock;
use Tests\Support\StockAdjustments;

/**
 * Refuser une demande, auditer la décision, et la rendre lisible au client
 * (Phase 5b §4 « Refuser », §6).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $this->a = CustomerPortalStock::location('A-01');
    $this->billy = CustomerPortalStock::item('ikea', 'ART-001');
    CustomerPortalStock::receive($this->billy, $this->a, '50');
    $this->request = StockAdjustments::request($this->billy, $this->a, '47.5');
});

it('requires an answer to reject', function (array $payload): void {
    StockAdjustments::reject($this->request, $payload)->assertUnprocessable()->assertJsonValidationErrors('reviewComment');

    expect($this->request->fresh()->status->value)->toBe('pending');
})->with([
    'missing' => [[]],
    'too short' => [['reviewComment' => 'no']],
    'too long' => [['reviewComment' => str_repeat('x', 2001)]],
]);

it('rejects without touching the stock', function (): void {
    $movements = StockMovement::count();

    StockAdjustments::reject($this->request)->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.reviewComment', 'Recompté : le stock est juste.')
        ->assertJsonPath('data.reviewer.id', authUser()->id)
        ->assertJsonPath('data.stockMovement', null)
        ->assertJsonPath('data.actions.canApprove', false)
        ->assertJsonPath('data.actions.canReject', false);

    expect(StockMovement::count())->toBe($movements)
        ->and(StockAdjustments::balance($this->billy, $this->a))->toBe('50.000')
        ->and($this->request->fresh()->reviewed_at)->not->toBeNull();
});

it('never approves a rejected request', function (): void {
    StockAdjustments::reject($this->request)->assertOk();

    StockAdjustments::approve($this->request)->assertStatus(409);
    StockAdjustments::reject($this->request)->assertStatus(409);
});

it('audits both decisions, without any secret', function (): void {
    StockAdjustments::reject($this->request)->assertOk();
    $other = StockAdjustments::request($this->billy, $this->a, '55');
    $movementId = StockAdjustments::approve($other)->assertOk()->json('data.stockMovement.id');

    $rejected = AuditLog::where('action', 'stock_adjustment_request.rejected')->where('entity_id', $this->request->id)->sole();
    $approved = AuditLog::where('action', 'stock_adjustment_request.approved')->where('entity_id', $other->id)->sole();

    expect($rejected->old_values)->toBe(['status' => 'pending'])
        ->and($rejected->new_values)->toBe(['status' => 'rejected'])
        ->and($rejected->user_id)->toBe(authUser()->id)
        ->and($approved->new_values['status'])->toBe('approved')
        ->and($approved->new_values['stock_movement_id'])->toBe($movementId)
        ->and(json_encode([$rejected->new_values, $approved->new_values]))->not->toContain('password')->not->toContain('token');
});

it('lets the customer read the decision and the answer in the portal', function (): void {
    StockAdjustments::reject($this->request)->assertOk();

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson("/api/v1/customer/stock/adjustment-requests/{$this->request->id}")
        ->assertOk()
        ->assertJsonPath('data.status.code', 'rejected')
        ->assertJsonPath('data.isPending', false)
        ->assertJsonPath('data.reviewComment', 'Recompté : le stock est juste.')
        ->assertJsonMissingPath('data.reviewer');
});
