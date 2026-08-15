<?php

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Modules\Stock\Models\StockReservation;

beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    // Membre sans aucune permission : ni owner, ni role porteur de droits.
    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);
    $this->powerless = $this->membership->user;

    $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->item = StockItem::factory()->forCustomer($customer)->create();
    $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);
    $depot = Depot::factory()->create(['agency_id' => $agency->id]);
    $this->location = StockLocation::factory()->forDepot($depot)->create();
    StockBalance::factory()->at($this->item, $this->location)->withQuantity(10)->create();
    $this->reservation = StockReservation::factory()->create([
        'stock_item_id' => $this->item->id,
        'stock_location_id' => $this->location->id,
    ]);

    $this->urls = [
        '/api/v1/stock-items',
        '/api/v1/stock-locations',
        '/api/v1/stock-balances',
        '/api/v1/stock-movements',
        '/api/v1/stock-reservations',
    ];
});

describe('missing permissions', function (): void {
    it('forbids reading each resource without the view permission', function (): void {
        foreach ($this->urls as $url) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->getJson($url)->assertForbidden();
        }
    });

    it('forbids the location tree without the view permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/stock-locations/tree')->assertForbidden();
    });

    it('forbids creating without the create permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-items', [
                'customerId' => $this->item->customer_id, 'articleCode' => 'X', 'status' => 'active',
            ])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/stock-movements', [
                'stockItemId' => $this->item->id, 'movementType' => 'x',
                'quantity' => 1, 'destinationLocationId' => $this->location->id,
            ])->assertForbidden();
    });

    it('forbids releasing without the release permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/stock-reservations/{$this->reservation->id}/release", ['status' => 'released'])
            ->assertForbidden();
    });

    it('grants read access once the view permissions are attached', function (): void {
        $role = Role::factory()->forOrganization($this->organization)->create();
        $permissions = Permission::whereIn('code', [
            'stock_items.view', 'stock_locations.view', 'stock_balances.view',
            'stock_movements.view', 'stock_reservations.view',
        ])->pluck('id');

        foreach ($permissions as $permissionId) {
            RolePermission::create(['role_id' => $role->id, 'permission_id' => $permissionId]);
        }

        UserRole::create(['organization_user_id' => $this->membership->id, 'role_id' => $role->id]);

        foreach ($this->urls as $url) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->getJson($url)->assertOk();
        }

        // La lecture est ouverte, l'ecriture reste fermee.
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/stock-items/{$this->item->id}")->assertForbidden();
    });

    it('requires the organization header on every route', function (): void {
        $user = authUser();

        foreach ($this->urls as $url) {
            $this->actingAs($user, 'sanctum')->getJson($url)->assertForbidden();
        }
    });

    it('rejects unauthenticated access', function (): void {
        foreach ($this->urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    });
});
