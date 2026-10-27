<?php

use App\Modules\CustomerPortal\Models\CustomerUserRole;
use App\Modules\Identity\Models\Role;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Chaque geste exige sa permission `customer.orders.*`.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->order = CustomerPortalOrders::order('ikea');
});

it('lets a viewer list and read, but nothing else', function (): void {
    $viewer = CustomerPortal::headers('viewer@ikea.test');
    $id = $this->order->id;

    $this->withHeaders($viewer)->getJson('/api/v1/customer/orders')->assertOk();
    $this->withHeaders($viewer)->getJson("/api/v1/customer/orders/$id")
        ->assertOk()
        ->assertJsonPath('data.actions.canEdit', false)
        ->assertJsonPath('data.actions.canCancel', false)
        ->assertJsonPath('data.actions.canDuplicate', false);

    $this->withHeaders($viewer)->postJson('/api/v1/customer/orders', CustomerPortalOrders::payload())->assertForbidden();
    $this->withHeaders($viewer)->putJson("/api/v1/customer/orders/$id", [])->assertForbidden();
    $this->withHeaders($viewer)->postJson("/api/v1/customer/orders/$id/duplicate")->assertForbidden();
    $this->withHeaders($viewer)->postJson("/api/v1/customer/orders/$id/cancel")->assertForbidden();
    $this->withHeaders($viewer)->postJson("/api/v1/customer/orders/$id/documents")->assertForbidden();
});

it('gives the order manager every order action', function (): void {
    $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))
        ->getJson("/api/v1/customer/orders/{$this->order->id}")
        ->assertOk()
        ->assertJsonPath('data.actions.canEdit', true)
        ->assertJsonPath('data.actions.canCancel', true)
        ->assertJsonPath('data.actions.canDuplicate', true);
});

it('closes orders to a stock manager', function (): void {
    $stock = CustomerPortal::member('viewer@ikea.test');
    $role = Role::where('organization_id', $stock->customer->organization_id)
        ->where('code', 'CUSTOMER_STOCK_MANAGER')->firstOrFail();
    CustomerUserRole::where('customer_user_id', $stock->id)->update(['role_id' => $role->id]);

    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson('/api/v1/customer/orders')->assertForbidden();
});

it('computes the actions of each order in the list', function (): void {
    $row = $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))
        ->getJson('/api/v1/customer/orders')->json('data.0');

    expect($row['actions'])->toHaveKeys(['canView', 'canEdit', 'canDuplicate', 'canCancel', 'canTrack'])
        ->and($row['status'])->toHaveKeys(['code', 'label']);
});
