<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockMovement;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalStock;

/**
 * Demandes de correction de stock (§5, §19, §30 « Change request ») : le
 * client signale un écart, le stock ne bouge pas.
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
    $this->qoqa = CustomerPortalStock::item('qoqa', 'QOQA-001');
    CustomerPortalStock::receive($this->qoqa, $this->a, '30');
    $this->payload = ['stockItemId' => $this->billy->id, 'stockLocationId' => $this->a->id, 'requestedQuantity' => '47.5', 'reason' => 'Inventaire du 30 septembre', 'comment' => 'Deux cartons abîmés'];
});

function adjust(array $payload, string $email = 'admin@ikea.test'): TestResponse
{
    return test()->withHeaders(CustomerPortal::headers($email))->postJson('/api/v1/customer/stock/adjustment-requests', $payload);
}

it('records the request with the displayed quantity read by the backend, and leaves the stock untouched', function (): void {
    $movements = StockMovement::count();

    $id = adjust($this->payload)->assertCreated()
        ->assertJsonPath('data.displayedQuantity', '50.000')
        ->assertJsonPath('data.requestedQuantity', '47.500')
        ->assertJsonPath('data.difference', '-2.500')
        ->assertJsonPath('data.status.code', 'pending')
        ->assertJsonPath('data.status.label', 'En attente')
        ->assertJsonPath('data.location.locationCode', 'A-01')
        ->json('data.id');

    $request = StockAdjustmentRequest::findOrFail($id);

    expect($request->customer_id)->toBe(CustomerPortal::customer('ikea')->id)
        ->and($request->requested_by)->toBe(CustomerPortal::member('admin@ikea.test')->user_id)
        ->and(StockBalance::where('stock_item_id', $this->billy->id)->where('stock_location_id', $this->a->id)->value('quantity'))->toBe('50.000')
        ->and(StockMovement::count())->toBe($movements)
        ->and(AuditLog::where('action', 'stock_adjustment_request.created')->where('entity_id', $id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'customer_portal.stock_adjustment_request_created')->where('entity_id', $id)->exists())->toBeTrue();
});

it('compares with the item total when no location is given', function (): void {
    adjust([...$this->payload, 'stockLocationId' => null, 'requestedQuantity' => '118'])->assertCreated()
        ->assertJsonPath('data.displayedQuantity', '120.000')
        ->assertJsonPath('data.location', null);
});

it('refuses a request without any difference, or at a location without stock', function (): void {
    adjust([...$this->payload, 'requestedQuantity' => '50'])->assertUnprocessable()->assertJsonValidationErrors('requestedQuantity');
    adjust([...$this->payload, 'stockLocationId' => CustomerPortalStock::location('C-99')->id])->assertUnprocessable()->assertJsonValidationErrors('stockLocationId');
});

it('refuses a second pending request for the same item and location', function (): void {
    adjust($this->payload)->assertCreated();

    adjust([...$this->payload, 'requestedQuantity' => '45'])->assertStatus(409);
    adjust([...$this->payload, 'stockLocationId' => $this->b->id, 'requestedQuantity' => '69'])->assertCreated();
});

it('refuses every field the carrier or the backend sets', function (string $field, mixed $value): void {
    adjust([...$this->payload, $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'displayedQuantity' => ['displayedQuantity', 999], 'status' => ['status', 'approved'],
    'customerId' => ['customerId', '01J0000000000000000000000X'], 'stockMovementId' => ['stockMovementId', '01J0000000000000000000000X'],
]);

it('answers 404 for the item of another customer, and hides its requests', function (): void {
    adjust([...$this->payload, 'stockItemId' => $this->qoqa->id])->assertNotFound();

    $qoqaRequest = StockAdjustmentRequest::factory()->forItem($this->qoqa)->create();
    $headers = CustomerPortal::headers('admin@ikea.test');

    $this->withHeaders($headers)->getJson("/api/v1/customer/stock/adjustment-requests/{$qoqaRequest->id}")->assertNotFound();
    $this->withHeaders($headers)->getJson('/api/v1/customer/stock/adjustment-requests')->assertOk()->assertJsonPath('meta.total', 0);
});

it('lists and shows its own requests, most recent first', function (): void {
    $id = adjust($this->payload)->json('data.id');
    $headers = CustomerPortal::headers('admin@ikea.test');

    $this->withHeaders($headers)->getJson('/api/v1/customer/stock/adjustment-requests')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->withHeaders($headers)->getJson("/api/v1/customer/stock/adjustment-requests/{$id}")->assertOk()->assertJsonPath('data.reason', 'Inventaire du 30 septembre');
    $this->withHeaders($headers)->getJson("/api/v1/customer/stock/adjustment-requests?stockItemId={$this->billy->id}&status[]=pending")->assertJsonPath('meta.total', 1);
});

it('offers the request only with the permission, and never a way to decide it', function (): void {
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson("/api/v1/customer/stock/items/{$this->billy->id}")
        ->assertJsonPath('data.actions.canRequestChange', true);
    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson("/api/v1/customer/stock/items/{$this->billy->id}")
        ->assertJsonPath('data.actions.canRequestChange', false);

    adjust($this->payload, 'viewer@ikea.test')->assertForbidden();

    $id = adjust($this->payload)->json('data.id');
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->patchJson("/api/v1/customer/stock/adjustment-requests/{$id}", ['status' => 'approved'])->assertStatus(405);
    expect(StockAdjustmentRequest::findOrFail($id)->isPending())->toBeTrue();
});
