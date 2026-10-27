<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\Role;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\UserStatus;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;

/**
 * Administration des membres d'un portail par son administrateur.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->admin = CustomerPortal::headers('admin@ikea.test');
    $this->viewer = CustomerPortal::member('viewer@ikea.test');
});

it('disables a member, revokes their portal tokens and audits it', function (): void {
    $session = CustomerPortal::token('viewer@ikea.test');

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->postJson("/api/v1/customer/users/{$this->viewer->id}/disable")
        ->assertOk()->assertJsonPath('data.status', 'disabled');

    $this->withHeaders(CustomerPortal::bearer($session))->getJson('/api/v1/customer/context')->assertUnauthorized();
    expect(AuditLog::where('action', 'customer_portal.user_disabled')->where('entity_id', $this->viewer->id)->exists())->toBeTrue();
});

it('re-enables a disabled member', function (): void {
    $this->viewer->update(['status' => UserStatus::DISABLED, 'disabled_at' => now()]);

    $this->withHeaders($this->admin)->postJson("/api/v1/customer/users/{$this->viewer->id}/enable")
        ->assertOk()->assertJsonPath('data.status', 'active');
});

it('forbids disabling oneself or the owner', function (): void {
    $admin = CustomerPortal::member('admin@ikea.test');

    $this->withHeaders($this->admin)->postJson("/api/v1/customer/users/{$admin->id}/disable")->assertUnprocessable();

    $admin->update(['is_owner' => false]);
    $this->viewer->update(['is_owner' => true]);
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->postJson("/api/v1/customer/users/{$this->viewer->id}/disable")->assertUnprocessable();
});

it('replaces the roles of a member and audits the change', function (): void {
    $orderManager = Role::where('organization_id', authOrganization()->id)->where('code', 'CUSTOMER_ORDER_MANAGER')->value('id');

    $this->withHeaders($this->admin)
        ->putJson("/api/v1/customer/users/{$this->viewer->id}/roles", ['roleIds' => [$orderManager]])
        ->assertOk()
        ->assertJsonPath('data.roles.0.code', 'CUSTOMER_ORDER_MANAGER');

    $log = AuditLog::where('action', 'customer_portal.roles_changed')->latest('created_at')->firstOrFail();
    expect($log->old_values['roles'])->toBe(['CUSTOMER_VIEWER'])
        ->and($log->new_values['roles'])->toBe(['CUSTOMER_ORDER_MANAGER']);
});

it('rejects an internal role id', function (): void {
    $internal = Role::where('organization_id', authOrganization()->id)->where('code', 'admin')->value('id');

    $this->withHeaders($this->admin)
        ->putJson("/api/v1/customer/users/{$this->viewer->id}/roles", ['roleIds' => [$internal]])
        ->assertUnprocessable();
});

it('updates the identity of a member whose account is not shared', function (): void {
    $this->withHeaders($this->admin)
        ->putJson("/api/v1/customer/users/{$this->viewer->id}", ['firstName' => 'Véra', 'phone' => '+41 21 000 00 00'])
        ->assertOk()
        ->assertJsonPath('data.user.firstName', 'Véra');
});

it('refuses to rename an account that is also used by the carrier', function (): void {
    OrganizationUser::create([
        'organization_id' => authOrganization()->id, 'user_id' => $this->viewer->user_id, 'status' => UserStatus::ACTIVE,
    ]);

    $this->withHeaders($this->admin)
        ->putJson("/api/v1/customer/users/{$this->viewer->id}", ['firstName' => 'Pirate'])
        ->assertUnprocessable();
});

it('lets a member update their own profile', function (): void {
    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))
        ->putJson('/api/v1/customer/profile', ['firstName' => 'Vera-Lou'])
        ->assertOk()
        ->assertJsonPath('data.firstName', 'Vera-Lou');
});
