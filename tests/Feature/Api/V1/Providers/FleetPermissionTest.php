<?php

use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Providers\Models\Provider;

beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    // Membre sans aucune permission : ni owner, ni role porteur de droits.
    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);
    $this->powerless = $this->membership->user;

    $this->provider = Provider::factory()->forOrganization($this->organization)->create();
    $this->type = VehicleType::factory()->forOrganization($this->organization)->create();
    $this->driver = Driver::factory()->forProvider($this->provider)->create();
    $this->vehicle = Vehicle::factory()->forProvider($this->provider)->ofType($this->type)->create();
});

describe('missing permissions', function (): void {
    it('forbids reading each resource without the view permission', function (): void {
        foreach ([
            '/api/v1/providers',
            '/api/v1/drivers',
            '/api/v1/vehicle-types',
            '/api/v1/vehicles',
        ] as $url) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->getJson($url)->assertForbidden();
        }
    });

    it('forbids creating each resource without the create permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/providers', ['code' => 'X', 'name' => 'X', 'status' => 'active'])
            ->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/vehicle-types', ['code' => 'X', 'name' => 'X', 'status' => 'active'])
            ->assertForbidden();
    });

    it('forbids updating and deleting without the matching permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/providers/{$this->provider->id}", ['name' => 'X'])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/drivers/{$this->driver->id}")->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/vehicles/{$this->vehicle->id}")->assertForbidden();
    });

    it('grants access once the matching permission is attached to the role', function (): void {
        $role = Role::factory()->forOrganization($this->organization)->create();
        $viewPermissions = Permission::whereIn('code', [
            'providers.view', 'drivers.view', 'vehicle_types.view', 'vehicles.view',
        ])->pluck('id');

        foreach ($viewPermissions as $permissionId) {
            RolePermission::create(['role_id' => $role->id, 'permission_id' => $permissionId]);
        }

        UserRole::create(['organization_user_id' => $this->membership->id, 'role_id' => $role->id]);

        foreach ([
            '/api/v1/providers',
            '/api/v1/drivers',
            '/api/v1/vehicle-types',
            '/api/v1/vehicles',
        ] as $url) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->getJson($url)->assertOk();
        }

        // La lecture est ouverte, l'ecriture reste fermee.
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/providers/{$this->provider->id}")->assertForbidden();
    });

    it('requires the organization header on every fleet route', function (): void {
        $user = authUser();

        foreach ([
            '/api/v1/providers',
            '/api/v1/drivers',
            '/api/v1/vehicle-types',
            '/api/v1/vehicles',
        ] as $url) {
            $this->actingAs($user, 'sanctum')->getJson($url)->assertForbidden();
        }
    });

    it('rejects unauthenticated access', function (): void {
        $this->getJson('/api/v1/providers')->assertUnauthorized();
        $this->getJson('/api/v1/vehicles')->assertUnauthorized();
    });
});
