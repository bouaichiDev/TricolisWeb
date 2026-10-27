<?php

use App\Modules\CustomerPortal\Models\CustomerUserRole;
use App\Modules\Identity\Models\Role;
use App\Shared\Enums\RoleScope;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CustomerPortal;

/**
 * Les permissions `customer.*` décident de tout, côté backend.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    Mail::fake();
});

it('lets a CUSTOMER_ADMIN invite a user', function (): void {
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->postJson('/api/v1/customer/users/invite', ['email' => 'nouveau@ikea.test'])
        ->assertCreated();
});

it('refuses an invitation from a CUSTOMER_VIEWER', function (): void {
    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))
        ->postJson('/api/v1/customer/users/invite', ['email' => 'nouveau@ikea.test'])
        ->assertForbidden();
});

it('refuses user administration to a CUSTOMER_ORDER_MANAGER', function (string $method, string $uri): void {
    $target = CustomerPortal::member('viewer@ikea.test')->id;

    $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))
        ->json($method, str_replace('{id}', $target, $uri), ['roleIds' => []])
        ->assertForbidden();
})->with([
    ['GET', '/api/v1/customer/users'],
    ['GET', '/api/v1/customer/users/{id}'],
    ['POST', '/api/v1/customer/users/{id}/disable'],
    ['PUT', '/api/v1/customer/users/{id}/roles'],
    ['GET', '/api/v1/customer/roles'],
]);

it('exposes effective permissions that match the role matrix', function (): void {
    $permissions = $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))
        ->getJson('/api/v1/customer/permissions')->assertOk()->json('data.permissions');

    expect($permissions)->toContain('customer.orders.create', 'customer.calendar.view')
        ->not->toContain('customer.users.view', 'customer.stock.adjustment_requests.create');
});

it('bounds permissions by the modules the carrier opened to the customer', function (): void {
    $customer = CustomerPortal::customer('ikea');
    $customer->update(['stock_enabled' => false, 'tracking_enabled' => false]);

    $permissions = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson('/api/v1/customer/permissions')->json('data.permissions');

    expect(collect($permissions)->filter(fn (string $code) => str_starts_with($code, 'customer.stock.')))->toBeEmpty()
        ->and($permissions)->not->toContain('customer.tracking.view', 'customer.orders.track')
        ->and($permissions)->toContain('customer.orders.view');

    $customer->update(['stock_enabled' => true]);
    $permissions = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson('/api/v1/customer/permissions')->json('data.permissions');

    expect($permissions)->toContain('customer.stock.view');
});

it('ignores internal permissions carried by a customer role', function (): void {
    $role = Role::where('code', 'CUSTOMER_VIEWER')->where('organization_id', authOrganization()->id)->firstOrFail();
    givePermissions($role, ['users.create', 'customers.delete']);

    $permissions = $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))
        ->getJson('/api/v1/customer/permissions')->json('data.permissions');

    expect($permissions)->not->toContain('users.create', 'customers.delete');
});

it('lists only the customer roles of the carrier', function (): void {
    $roles = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson('/api/v1/customer/roles')->assertOk()->json('data');

    expect(collect($roles)->pluck('code')->sort()->values()->all())
        ->toBe(['CUSTOMER_ADMIN', 'CUSTOMER_ORDER_MANAGER', 'CUSTOMER_STOCK_MANAGER', 'CUSTOMER_VIEWER']);
});

it('forbids granting a role that carries more than the granter holds', function (): void {
    $organization = authOrganization();
    $delegate = Role::create([
        'organization_id' => $organization->id, 'code' => 'CUSTOMER_DELEGATE', 'name' => 'Délégué',
        'scope' => RoleScope::CUSTOMER->value, 'is_system' => false, 'status' => 'active',
    ]);
    givePermissions($delegate, ['customer.users.view', 'customer.users.assign_role']);
    $orders = CustomerPortal::member('orders@ikea.test');
    CustomerUserRole::create(['customer_user_id' => $orders->id, 'role_id' => $delegate->id]);

    $admin = Role::where('code', 'CUSTOMER_ADMIN')->where('organization_id', $organization->id)->value('id');

    $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))
        ->putJson('/api/v1/customer/users/'.CustomerPortal::member('viewer@ikea.test')->id.'/roles', ['roleIds' => [$admin]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('roleIds');
});
