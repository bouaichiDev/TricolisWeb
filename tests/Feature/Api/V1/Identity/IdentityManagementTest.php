<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

it('creates an organization role and assigns permissions', function (): void {
    $permission = Permission::firstOrFail();
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->postJson('/api/v1/roles', [
        'code' => 'dispatcher', 'name' => 'Dispatcher', 'scope' => 'organization', 'isSystem' => false, 'status' => 'active', 'permissionIds' => [$permission->id],
    ])->assertCreated()->assertJsonPath('data.code', 'dispatcher')->assertJsonCount(1, 'data.permissions');
    $this->assertDatabaseHas('roles', ['organization_id' => $this->organization->id, 'code' => 'dispatcher']);
});

it('creates a user membership and attaches a role to the membership', function (): void {
    // Un rôle **local et non système** : le rôle `admin` semé porte toutes les
    // permissions de l'organisation, l'attribuer contournerait le plafond de
    // délégation. C'est le rôle que ce test prenait auparavant.
    $role = organizationRole($this->organization, 'operateur');
    $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->postJson('/api/v1/organization-users', [
        'firstName' => 'Nouveau', 'lastName' => 'Compte', 'email' => 'nouveau@example.test', 'password' => 'Strong-password-123!', 'password_confirmation' => 'Strong-password-123!', 'preferredLanguage' => 'fr', 'isOwner' => false, 'isPrimary' => true, 'status' => 'active', 'roleIds' => [$role->id],
    ])->assertCreated()->assertJsonPath('data.user.email', 'nouveau@example.test')->assertJsonCount(1, 'data.roles');
    $membership = OrganizationUser::findOrFail($response->json('data.id'));
    expect($membership->roles()->whereKey($role->id)->exists())->toBeTrue();
});

it('rejects a role from another organization', function (): void {
    $other = Organization::factory()->create();
    $role = Role::create(['organization_id' => $other->id, 'code' => 'other', 'name' => 'Other', 'scope' => 'organization', 'is_system' => false, 'status' => 'active']);
    $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)->postJson('/api/v1/organization-users', [
        'firstName' => 'Refusé', 'lastName' => 'Compte', 'email' => 'refuse@example.test', 'password' => 'Strong-password-123!', 'password_confirmation' => 'Strong-password-123!', 'preferredLanguage' => 'fr', 'isOwner' => false, 'isPrimary' => false, 'status' => 'active', 'roleIds' => [$role->id],
        // Le refus porte désormais sur `roleIds` et non sur `roleIds.0` : la
        // vérification est faite en bloc par le garde d'affectation, qui ne dit
        // pas lequel des rôles est en cause — le préciser révélerait lesquels
        // existent ailleurs.
    ])->assertUnprocessable()->assertJsonValidationErrors('roleIds');
});
