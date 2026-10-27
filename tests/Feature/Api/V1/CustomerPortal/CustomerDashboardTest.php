<?php

use App\Modules\CustomerPortal\Models\CustomerUserRole;
use App\Modules\Identity\Models\Role;
use App\Modules\Orders\Models\Order;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Tableau de bord du portail : le moteur interne, borné au client courant,
 * configuré par le transporteur sur les rôles `CUSTOMER_*`.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->organization = authOrganization();
    $this->internal = ['X-Organization-Id' => $this->organization->id];
    $this->viewerRole = Role::where('organization_id', $this->organization->id)->where('code', 'CUSTOMER_VIEWER')->firstOrFail();
});

function widgetsOf(array $response): array
{
    return collect($response['data']['widgets'])->keyBy('key')->all();
}

it('serves the default customer widgets, computed for the current customer only', function (): void {
    $widgets = widgetsOf($this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson('/api/v1/customer/dashboard')->assertOk()->json());
    $ikea = CustomerPortal::customer('ikea');

    expect($widgets)->toHaveKeys(['customer_orders_count', 'customer_orders_by_status', 'customer_recent_orders', 'customer_new_order'])
        ->and($widgets['customer_orders_count']['data']['value'])->toBe(Order::where('customer_id', $ikea->id)->count())
        ->and($widgets['customer_orders_count']['label'])->toBe('Commandes passées');

    $titles = collect($widgets['customer_recent_orders']['data']['items'])->pluck('title');
    expect($titles)->not->toContain(CustomerPortalOrders::order('qoqa')->order_number);
});

it('never serves an internal widget to a customer', function (): void {
    $keys = array_keys(widgetsOf($this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson('/api/v1/customer/dashboard')->json()));

    expect(array_filter($keys, static fn (string $key): bool => ! str_starts_with($key, 'customer_')))->toBe([]);
});

it('drops the widgets whose permission the member lacks', function (): void {
    $widgets = widgetsOf($this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson('/api/v1/customer/dashboard')->json());

    expect($widgets)->toHaveKey('customer_orders_count')->not->toHaveKey('customer_new_order');
});

it('lets the carrier compose the dashboard of a customer role', function (): void {
    $this->actingAs(authUser(), 'sanctum')->withHeaders($this->internal)
        ->putJson("/api/v1/roles/{$this->viewerRole->id}/dashboard", ['widgets' => [['key' => 'customer_recent_orders', 'position' => 1]]])
        ->assertOk();

    $widgets = widgetsOf($this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson('/api/v1/customer/dashboard')->json());

    expect(array_keys($widgets))->toBe(['customer_recent_orders']);
});

it('refuses an internal widget on a customer role, and a customer widget on an internal role', function (): void {
    $this->actingAs(authUser(), 'sanctum')->withHeaders($this->internal)
        ->putJson("/api/v1/roles/{$this->viewerRole->id}/dashboard", ['widgets' => [['key' => 'orders_today', 'position' => 1]]])
        ->assertUnprocessable();

    $internal = organizationRole($this->organization, 'operateur');
    $this->withHeaders($this->internal)
        ->putJson("/api/v1/roles/{$internal->id}/dashboard", ['widgets' => [['key' => 'customer_orders_count', 'position' => 1]]])
        ->assertUnprocessable();
});

it('offers the customer catalogue when configuring a customer role', function (): void {
    $keys = collect($this->actingAs(authUser(), 'sanctum')->withHeaders($this->internal)
        ->getJson("/api/v1/roles/{$this->viewerRole->id}/dashboard")->assertOk()->json('data'))->pluck('key');

    expect($keys)->toContain('customer_orders_count')->not->toContain('orders_today');
});

it('lists customer roles only on demand in the internal administration', function (): void {
    $default = collect($this->actingAs(authUser(), 'sanctum')->withHeaders($this->internal)->getJson('/api/v1/roles?perPage=100')->json('data'))->pluck('code');
    $customer = collect($this->withHeaders($this->internal)->getJson('/api/v1/roles?perPage=100&scope=customer')->json('data'))->pluck('code');

    expect($default)->not->toContain('CUSTOMER_VIEWER')
        ->and($customer)->toContain('CUSTOMER_VIEWER')->not->toContain('admin');
});

it('keeps an unconfigured member without roles on an empty dashboard', function (): void {
    CustomerUserRole::where('customer_user_id', CustomerPortal::member('viewer@ikea.test')->id)->delete();

    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson('/api/v1/customer/dashboard')
        ->assertOk()
        ->assertJsonCount(0, 'data.widgets');
});
