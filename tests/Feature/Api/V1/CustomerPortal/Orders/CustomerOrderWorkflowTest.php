<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Tours\Models\TourStopService;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Modification, annulation, duplication : le workflow existant décide, et
 * une commande planifiée se traite avec le transporteur.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->headers = CustomerPortal::headers('orders@ikea.test');
    $this->order = CustomerPortalOrders::order('ikea');
    $this->uri = "/api/v1/customer/orders/{$this->order->id}";
});

function versioned(Order $order, array $overrides = []): array
{
    return [...CustomerPortalOrders::payload($overrides), 'updatedAt' => $order->fresh()->updated_at->toIso8601String()];
}

it('rewrites an editable order and audits it', function (): void {
    $this->withHeaders($this->headers)->putJson($this->uri, versioned($this->order, ['customerReference' => 'IKEA-NEW']))
        ->assertOk()
        ->assertJsonPath('data.customerReference', 'IKEA-NEW')
        ->assertJsonCount(2, 'data.lines');

    expect(AuditLog::where('action', 'customer_portal.order_updated')->where('entity_id', $this->order->id)->exists())->toBeTrue()
        ->and($this->order->fresh()->order_date->equalTo($this->order->order_date))->toBeTrue();
});

it('answers ORDER_VERSION_CONFLICT when the order changed since it was read', function (): void {
    $payload = versioned($this->order);
    $this->travel(2)->seconds();
    $this->order->touch();

    $this->withHeaders($this->headers)->putJson($this->uri, $payload)
        ->assertConflict()
        ->assertJsonPath('code', 'ORDER_VERSION_CONFLICT');
});

it('refuses to edit or cancel a planned order', function (): void {
    CustomerPortalOrders::plan($this->order);

    $this->withHeaders($this->headers)->getJson($this->uri)
        ->assertJsonPath('data.actions.canEdit', false)
        ->assertJsonPath('data.actions.canCancel', false);
    $this->withHeaders($this->headers)->putJson($this->uri, versioned($this->order))
        ->assertConflict()->assertJsonPath('code', 'ORDER_NOT_EDITABLE');
    $this->withHeaders($this->headers)->postJson("$this->uri/cancel", ['reason' => 'Erreur'])
        ->assertConflict()->assertJsonPath('code', 'ORDER_NOT_CANCELLABLE');
});

it('refuses to edit an order whose status closes its content', function (): void {
    $this->order->forceFill(['status' => OrderStatus::COMPLETED])->save();

    $this->withHeaders($this->headers)->putJson($this->uri, versioned($this->order))
        ->assertConflict()->assertJsonPath('code', 'ORDER_NOT_EDITABLE');
});

it('cancels through the workflow, with a reason, without deleting', function (): void {
    $this->withHeaders($this->headers)->postJson("$this->uri/cancel", ['reason' => 'Erreur dans la commande'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.actions.canCancel', false);

    expect(Order::whereKey($this->order->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'customer_portal.order_cancelled')->exists())->toBeTrue();
});

it('requires the reason the workflow asks for', function (): void {
    $this->withHeaders($this->headers)->postJson("$this->uri/cancel", [])->assertUnprocessable();
});

it('duplicates into a fresh order, without any execution data', function (): void {
    CustomerPortalOrders::plan($this->order);

    $response = $this->withHeaders($this->headers)->postJson("$this->uri/duplicate")->assertCreated();
    $copy = Order::findOrFail($response->json('data.id'));

    expect($copy->id)->not->toBe($this->order->id)
        ->and($copy->order_number)->not->toBe($this->order->order_number)
        ->and($copy->customer_id)->toBe($this->order->customer_id)
        ->and($copy->source)->toBe(OrderSource::CUSTOMER_PORTAL)
        ->and($copy->lines()->count())->toBe($this->order->lines()->count())
        ->and($copy->orderServices()->count())->toBe($this->order->orderServices()->count())
        ->and(TourStopService::whereIn('order_service_id', $copy->orderServices()->pluck('id'))->exists())->toBeFalse();
});
