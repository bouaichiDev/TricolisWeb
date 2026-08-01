<?php

use App\Modules\Claims\Models\Claim;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Tracking\Models\TrackingEvent;

beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    // Membre sans aucune permission : ni owner, ni role porteur de droits.
    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);
    $this->powerless = $this->membership->user;

    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->order = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $this->customer->id]);
    $this->event = TrackingEvent::factory()->forOrder($this->order)->create();
    $this->proof = ProofOfDelivery::factory()->forOrder($this->order)->create();
    $this->claim = Claim::factory()->forCustomer($this->customer)->create();

    $this->urls = ['/api/v1/tracking-events', '/api/v1/proofs-of-delivery', '/api/v1/claims'];
});

describe('missing permissions', function (): void {
    it('forbids reading each resource without the view permission', function (): void {
        foreach ($this->urls as $url) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->getJson($url)->assertForbidden();
        }
    });

    it('forbids creating without the create permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/tracking-events', [
                'orderId' => $this->order->id, 'eventType' => 'x',
                'status' => 'y', 'occurredAt' => '2026-09-01T08:00:00Z',
            ])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/proofs-of-delivery', [
                'orderId' => $this->order->id, 'recipientName' => 'X',
                'deliveredAt' => '2026-09-01T08:00:00Z',
            ])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/claims', [
                'customerId' => $this->customer->id, 'title' => 'X',
                'claimType' => 'damage', 'status' => 'open',
            ])->assertForbidden();
    });

    it('forbids updating and deleting a claim without the matching permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/claims/{$this->claim->id}", ['title' => 'X'])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/claims/{$this->claim->id}")->assertForbidden();
    });

    it('grants read access once the view permissions are attached', function (): void {
        $role = Role::factory()->forOrganization($this->organization)->create();
        $permissions = Permission::whereIn('code', [
            'tracking_events.view', 'proofs_of_delivery.view', 'claims.view',
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
            ->deleteJson("/api/v1/claims/{$this->claim->id}")->assertForbidden();
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
