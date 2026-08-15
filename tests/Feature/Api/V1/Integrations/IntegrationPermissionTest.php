<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Organizations\Models\OrganizationUser;

beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    // Membre sans aucune permission : ni owner, ni role porteur de droits.
    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);
    $this->powerless = $this->membership->user;

    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->import = CustomerImportConfiguration::factory()->forCustomer($this->customer)->create();
    $this->api = CustomerApiConfiguration::factory()->forCustomer($this->customer)->create();
    $this->export = CustomerExportConfiguration::factory()->forCustomer($this->customer)->create();
    $this->job = ExportJob::factory()->forConfiguration($this->export)->failed()->create();

    $this->urls = [
        '/api/v1/customer-import-configurations',
        '/api/v1/customer-api-configurations',
        '/api/v1/customer-export-configurations',
        '/api/v1/export-jobs',
    ];
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
            ->postJson('/api/v1/customer-api-configurations', [
                'customerId' => $this->customer->id, 'name' => 'X',
            ])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/export-jobs', [
                'configurationId' => $this->export->id, 'status' => 'pending',
            ])->assertForbidden();
    });

    it('forbids rotating a key without the dedicated permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customer-api-configurations/{$this->api->id}/rotate-key")
            ->assertForbidden();
    });

    it('forbids retrying an export without the dedicated permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/export-jobs/{$this->job->id}/retry", ['status' => 'pending'])
            ->assertForbidden();
    });

    it('grants read access once the view permissions are attached', function (): void {
        $role = Role::factory()->forOrganization($this->organization)->create();
        $permissions = Permission::whereIn('code', [
            'customer_import_configurations.view',
            'customer_api_configurations.view',
            'customer_export_configurations.view',
            'export_jobs.view',
        ])->pluck('id');

        foreach ($permissions as $permissionId) {
            RolePermission::create(['role_id' => $role->id, 'permission_id' => $permissionId]);
        }

        UserRole::create(['organization_user_id' => $this->membership->id, 'role_id' => $role->id]);

        foreach ($this->urls as $url) {
            $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
                ->getJson($url)->assertOk();
        }

        // Lire un acces API ne permet pas d'en renouveler la cle.
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customer-api-configurations/{$this->api->id}/rotate-key")
            ->assertForbidden();
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
