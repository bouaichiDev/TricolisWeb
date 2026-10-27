<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockItem;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalStock;
use Tests\Support\StockAdjustments;

/**
 * Liste, fiche et permissions des demandes de correction (Phase 5b §2, §3).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerPortalStock::enable();
    $this->a = CustomerPortalStock::location('A-01');
    $this->b = CustomerPortalStock::location('B-02');
    $this->billy = CustomerPortalStock::item('ikea', 'ART-001');
    $this->qoqa = CustomerPortalStock::item('qoqa', 'QOQA-001');
    CustomerPortalStock::receive($this->billy, $this->a, '50');
    CustomerPortalStock::receive($this->billy, $this->b, '70');
    CustomerPortalStock::receive($this->qoqa, $this->a, '30');

    $this->done = StockAdjustments::request($this->billy, $this->b, '69');
    StockAdjustments::reject($this->done)->assertOk();
    $this->travel(1)->minutes();
    $this->pending = StockAdjustments::request($this->billy, $this->a, '47.5');
    $this->travel(1)->minutes();
    $this->qoqaPending = StockAdjustments::request($this->qoqa, null, '28', 'admin@qoqa.test');
});

function adjustmentList(string $query = ''): TestResponse
{
    return StockAdjustments::internal()->getJson('/api/v1/stock-adjustment-requests'.$query);
}

it('lists pending requests first, then the newest', function (): void {
    adjustmentList()->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.id', $this->qoqaPending->id)
        ->assertJsonPath('data.1.id', $this->pending->id)
        ->assertJsonPath('data.2.id', $this->done->id)
        ->assertJsonPath('data.1.customer.code', 'ikea')
        ->assertJsonPath('data.1.stockItem.articleCode', 'ART-001')
        ->assertJsonPath('data.1.location.locationCode', 'A-01')
        ->assertJsonPath('data.1.difference', '-2.500')
        ->assertJsonPath('data.2.reviewer.id', authUser()->id);
});

it('filters by status, customer, item, location, period and search, and paginates', function (): void {
    $ikea = CustomerPortal::customer('ikea')->id;

    adjustmentList('?status[]=pending')->assertJsonPath('meta.total', 2);
    adjustmentList('?status[]=pending&status[]=rejected')->assertJsonPath('meta.total', 3);
    adjustmentList('?status=rejected')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $this->done->id);
    adjustmentList("?customerId={$ikea}")->assertJsonPath('meta.total', 2);
    adjustmentList("?stockItemId={$this->qoqa->id}")->assertJsonPath('meta.total', 1);
    adjustmentList("?stockLocationId={$this->a->id}")->assertJsonPath('meta.total', 1);
    adjustmentList('?search=QOQA-0')->assertJsonPath('meta.total', 1);
    adjustmentList('?search=Inventaire')->assertJsonPath('meta.total', 3);
    adjustmentList('?createdFrom='.now()->addDay()->toDateString())->assertJsonPath('meta.total', 0);
    adjustmentList('?perPage=2&page=2')->assertJsonPath('meta.total', 3)->assertJsonCount(1, 'data');
    adjustmentList('?status[]=unknown')->assertUnprocessable();
});

it('keeps the list to the active organization, without N+1', function (): void {
    $elsewhere = Organization::factory()->create();
    $foreignItem = StockItem::factory()->forCustomer(Customer::factory()->create(['organization_id' => $elsewhere->id]))->create();
    $foreign = StockAdjustmentRequest::factory()->forItem($foreignItem)->create();

    $count = function (int $total): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        adjustmentList()->assertJsonPath('meta.total', $total);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $few = $count(3);
    foreach (['ART-002', 'ART-003', 'ART-004'] as $code) {
        $item = CustomerPortalStock::item('ikea', $code);
        CustomerPortalStock::receive($item, $this->b, '10');
        StockAdjustments::request($item, $this->b, '9');
    }

    // Trois lignes de plus, pas une requête de plus.
    expect($count(6))->toBe($few);
    StockAdjustments::internal()->getJson("/api/v1/stock-adjustment-requests/{$foreign->id}")->assertNotFound();
    StockAdjustments::internal()->postJson("/api/v1/stock-adjustment-requests/{$foreign->id}/approve")->assertNotFound();
});

it('lists the requests of one customer for its stock tab', function (): void {
    $ikea = CustomerPortal::customer('ikea');

    StockAdjustments::internal()->getJson("/api/v1/customers/{$ikea->id}/stock-adjustment-requests")
        ->assertOk()->assertJsonPath('meta.total', 2);
});

it('shows the current quantity, the difference and the drift', function (): void {
    StockAdjustments::withdraw($this->billy, $this->a, '5');

    StockAdjustments::internal()->getJson("/api/v1/stock-adjustment-requests/{$this->pending->id}")->assertOk()
        ->assertJsonPath('data.displayedQuantity', '50.000')
        ->assertJsonPath('data.requestedQuantity', '47.500')
        ->assertJsonPath('data.currentQuantity', '45.000')
        ->assertJsonPath('data.difference', '-2.500')
        ->assertJsonPath('data.drift', '-5.000')
        ->assertJsonPath('data.movementType', 'adjustment')
        ->assertJsonPath('data.corrections.0.direction', 'inbound')
        ->assertJsonPath('data.corrections.0.quantity', '2.500')
        ->assertJsonPath('data.requester.name', CustomerPortal::member('admin@ikea.test')->user->fullName())
        ->assertJsonPath('data.actions.canApprove', true);

    StockAdjustments::internal()->getJson("/api/v1/stock-adjustment-requests/{$this->qoqaPending->id}")
        ->assertJsonPath('data.currentQuantity', '30.000')
        ->assertJsonPath('data.drift', '0.000');
});

it('answers 403 without the permissions, and hides the actions without review', function (): void {
    $membership = OrganizationUser::factory()->forOrganization(authOrganization())->create(['is_owner' => false]);
    $headers = ['X-Organization-Id' => authOrganization()->id];
    $as = fn () => $this->actingAs($membership->user, 'sanctum')->withHeaders($headers);

    $as()->getJson('/api/v1/stock-adjustment-requests')->assertForbidden();
    $as()->getJson("/api/v1/stock-adjustment-requests/{$this->pending->id}")->assertForbidden();

    $role = Role::factory()->forOrganization(authOrganization())->create();
    RolePermission::create(['role_id' => $role->id, 'permission_id' => Permission::where('code', 'stock_adjustment_requests.view')->value('id')]);
    UserRole::create(['organization_user_id' => $membership->id, 'role_id' => $role->id]);

    $as()->getJson("/api/v1/stock-adjustment-requests/{$this->pending->id}")->assertOk()->assertJsonPath('data.actions.canApprove', false);
    $as()->postJson("/api/v1/stock-adjustment-requests/{$this->pending->id}/approve")->assertForbidden();
    $as()->postJson("/api/v1/stock-adjustment-requests/{$this->pending->id}/reject", ['reviewComment' => 'Non.'])->assertForbidden();
});

it('seeds both permissions in the stock section, granted to the organization admin', function (): void {
    $codes = ['stock_adjustment_requests.view', 'stock_adjustment_requests.review'];

    expect(Permission::whereIn('code', $codes)->pluck('menu_section')->unique()->all())->toBe(['stock'])
        ->and(Role::where('organization_id', authOrganization()->id)->where('code', 'admin')->firstOrFail()
            ->permissions()->whereIn('code', $codes)->count())->toBe(2);
});
