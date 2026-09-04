<?php

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\PlatformAccess;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\RoleScope;

/**
 * Tentatives d'élévation d'un administrateur d'organisme vers la plateforme.
 *
 * Ces tests ne passent pas par l'interface : ils forgent la requête HTTP, comme
 * le ferait quelqu'un qui aurait lu le code du frontend. Masquer un bouton dans
 * React ne protège rien ; c'est ici que la protection doit tenir.
 *
 * L'utilisateur de test est propriétaire de son organisation — le profil le plus
 * favorable côté local, puisque `hasPermission()` accorde tout à un propriétaire.
 * S'il ne peut pas s'élever, personne d'un rang inférieur ne le peut.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
});

describe('organizations', function (): void {
    it('refuses organization creation to a local owner', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/organizations', ['code' => 'pirate', 'name' => 'Organisation pirate'])
            ->assertForbidden();

        $this->assertDatabaseMissing('organizations', ['code' => 'pirate']);
    });

    it('refuses updating an organization the user does not belong to', function (): void {
        $other = Organization::factory()->create(['name' => 'Intacte']);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/organizations/{$other->id}", ['name' => 'Détournée'])
            ->assertForbidden();

        $this->assertDatabaseHas('organizations', ['id' => $other->id, 'name' => 'Intacte']);
    });

    it('hides other organizations from a local owner but shows them to the platform', function (): void {
        Organization::factory()->count(2)->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
            ->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });
});

describe('role creation', function (): void {
    /**
     * §30 — la requête forgée du prompt, mot pour mot.
     *
     * `scope` et `isSystem` ne figurent plus dans les règles de validation :
     * ils sont donc absents de `validated()` et n'atteignent jamais la base. Le
     * rôle est créé, mais **local et non système** — l'élévation échoue là où
     * elle comptait.
     */
    it('ignores scope and isSystem sent by a local owner', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->postJson('/api/v1/roles', [
                'code' => 'SUPER_ADMIN',
                'name' => 'SuperAdmin',
                'scope' => 'platform',
                'isSystem' => true,
                'status' => 'active',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('roles', [
            'id' => $response->json('data.id'),
            'organization_id' => $this->organization->id,
            'scope' => RoleScope::ORGANIZATION->value,
            'is_system' => false,
        ]);

        $this->assertDatabaseMissing('roles', ['code' => 'SUPER_ADMIN', 'scope' => RoleScope::PLATFORM->value]);
    });

    /**
     * Le rôle ainsi créé ne confère aucune autorité plateforme.
     *
     * C'est le cœur de la correction : la sécurité tient à `scope`, pas au nom.
     * Un rôle appelé « SuperAdmin » reste un rôle local.
     */
    it('grants no platform authority to a role merely named SuperAdmin', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->postJson('/api/v1/roles', [
                'code' => 'SUPER_ADMIN',
                'name' => 'SuperAdmin',
                'status' => 'active',
            ])->assertCreated();

        expect(app(PlatformAccess::class)->isPlatformAdmin($this->user->fresh()))->toBeFalse();

        $this->actingAs($this->user->fresh(), 'sanctum')
            ->postJson('/api/v1/organizations', ['code' => 'pirate', 'name' => 'Organisation pirate'])
            ->assertForbidden();
    });
});

describe('permission delegation', function (): void {
    /**
     * §31 — un rôle local ne peut pas recevoir une permission plateforme.
     */
    it('refuses a platform permission on a local role', function (): void {
        $platformPermission = Permission::where('code', 'organizations.create')->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->postJson('/api/v1/roles', [
                'code' => 'MY_ROLE',
                'name' => 'Mon rôle',
                'status' => 'active',
                'permissionIds' => [$platformPermission->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissionIds');

        $this->assertDatabaseMissing('roles', ['code' => 'MY_ROLE']);
    });

    it('refuses adding a platform permission to an existing local role', function (): void {
        $role = organizationRole($this->organization, 'my_role');
        $platformPermission = Permission::where('code', 'organizations.delete')->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->patchJson("/api/v1/roles/{$role->id}", ['permissionIds' => [$platformPermission->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissionIds');

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $platformPermission->id,
        ]);
    });

    it('hides platform permissions from the reference list of a local owner', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->getJson('/api/v1/permissions')
            ->assertOk();

        $codes = collect($response->json('data'))->pluck('code');

        expect($codes)->not->toContain('organizations.create')
            ->and($codes)->not->toContain('organizations.delete')
            ->and($codes)->toContain('customers.view');
    });

    it('exposes platform permissions to a platform administrator', function (): void {
        $response = $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->getJson('/api/v1/permissions')
            ->assertOk();

        expect(collect($response->json('data'))->pluck('code'))->toContain('organizations.create');
    });

    /**
     * Le plafond de délégation ne s'applique pas qu'aux permissions plateforme.
     *
     * Un administrateur qui ne détient qu'une partie des droits ne peut pas en
     * accorder davantage — sans quoi il lui suffirait de créer un rôle complet
     * puis de se l'attribuer.
     */
    it('refuses delegating a permission the author does not hold', function (): void {
        $limitedRole = organizationRole($this->organization, 'limite');
        $roles = Permission::whereIn('code', ['roles.view', 'roles.create', 'roles.assign_permissions'])->pluck('id');

        foreach ($roles as $permissionId) {
            RolePermission::create(['role_id' => $limitedRole->id, 'permission_id' => $permissionId]);
        }

        $member = OrganizationUser::factory()->forOrganization($this->organization)->create();
        UserRole::create(['organization_user_id' => $member->id, 'role_id' => $limitedRole->id]);

        $customersDelete = Permission::where('code', 'customers.delete')->firstOrFail();

        $this->actingAs($member->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->postJson('/api/v1/roles', [
                'code' => 'trop_puissant',
                'name' => 'Trop puissant',
                'status' => 'active',
                'permissionIds' => [$customersDelete->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissionIds');
    });
});

describe('system and cross-organization roles', function (): void {
    it('refuses modifying a system role', function (): void {
        $systemRole = Role::where('organization_id', $this->organization->id)
            ->where('is_system', true)
            ->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->patchJson("/api/v1/roles/{$systemRole->id}", ['name' => 'Détourné'])
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $systemRole->id, 'name' => 'Administrateur']);
    });

    it('refuses deleting a system role', function (): void {
        $systemRole = Role::where('organization_id', $this->organization->id)
            ->where('is_system', true)
            ->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->deleteJson("/api/v1/roles/{$systemRole->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    });

    /**
     * Un rôle plateforme n'a pas d'organisation : il ne peut donc être ni vu ni
     * modifié depuis une administration locale. Le refus se présente comme une
     * absence — un 403 confirmerait son existence.
     */
    it('hides the platform role from a local owner', function (): void {
        $platformRole = Role::where('scope', RoleScope::PLATFORM->value)->whereNull('organization_id')->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->getJson("/api/v1/roles/{$platformRole->id}")
            ->assertNotFound();
    });

    it('omits the platform role from the local role listing', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->getJson('/api/v1/roles')
            ->assertOk();

        expect(collect($response->json('data'))->pluck('code'))->not->toContain('superadmin');
    });

    /**
     * §32 — un rôle d'une autre organisation ne peut pas être affecté.
     */
    it('refuses assigning a role that belongs to another organization', function (): void {
        $other = Organization::factory()->create();
        $foreignRole = organizationRole($other, 'externe');
        $member = OrganizationUser::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->patchJson("/api/v1/organization-users/{$member->id}", ['roleIds' => [$foreignRole->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('roleIds');

        $this->assertDatabaseMissing('user_roles', [
            'organization_user_id' => $member->id,
            'role_id' => $foreignRole->id,
        ]);
    });

    it('refuses assigning the platform role to a member', function (): void {
        $platformRole = Role::where('scope', RoleScope::PLATFORM->value)->whereNull('organization_id')->firstOrFail();
        $member = OrganizationUser::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->patchJson("/api/v1/organization-users/{$member->id}", ['roleIds' => [$platformRole->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('roleIds');

        $this->assertDatabaseMissing('user_roles', [
            'organization_user_id' => $member->id,
            'role_id' => $platformRole->id,
        ]);
    });

    it('refuses assigning the platform role to oneself', function (): void {
        $platformRole = Role::where('scope', RoleScope::PLATFORM->value)->whereNull('organization_id')->firstOrFail();
        $ownMembership = OrganizationUser::where('user_id', $this->user->id)->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->patchJson("/api/v1/organization-users/{$ownMembership->id}", ['roleIds' => [$platformRole->id]])
            ->assertStatus(422);

        expect(app(PlatformAccess::class)->isPlatformAdmin($this->user->fresh()))->toBeFalse();
    });

    it('refuses assigning a system role to a member', function (): void {
        $systemRole = Role::where('organization_id', $this->organization->id)
            ->where('is_system', true)
            ->firstOrFail();
        $member = OrganizationUser::factory()->forOrganization($this->organization)->create();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Organization-Id', $this->organization->id)
            ->patchJson("/api/v1/organization-users/{$member->id}", ['roleIds' => [$systemRole->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('roleIds');
    });
});

describe('seeded roles', function (): void {
    /**
     * Le rôle `admin` recevait auparavant toutes les permissions, y compris
     * `organizations.create` : chaque propriétaire d'organisme détenait donc le
     * droit de créer d'autres organisations.
     */
    it('keeps platform permissions out of the seeded organization admin role', function (): void {
        $adminRole = Role::where('organization_id', $this->organization->id)
            ->where('code', 'admin')
            ->with('permissions')
            ->firstOrFail();

        $codes = $adminRole->permissions->pluck('code');

        expect($codes)->not->toContain(...PlatformAccess::PLATFORM_PERMISSIONS)
            ->and($codes)->toContain('customers.view');
    });

    it('seeds exactly one organization-less platform role, attached to nobody', function (): void {
        $platformRoles = Role::where('scope', RoleScope::PLATFORM->value)->get();

        expect($platformRoles)->toHaveCount(1)
            ->and($platformRoles->first()->organization_id)->toBeNull();

        $this->assertDatabaseMissing('user_roles', ['role_id' => $platformRoles->first()->id]);
    });
});
