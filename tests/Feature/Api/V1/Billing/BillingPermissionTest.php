<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Providers\Models\Provider;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;

beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    // Membre sans aucune permission : ni owner, ni role porteur de droits.
    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);
    $this->powerless = $this->membership->user;

    $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->invoice = Invoice::factory()->forCustomer($customer)->create();
    $this->line = InvoiceLine::factory()->forInvoice($this->invoice)->create();

    $provider = Provider::factory()->forOrganization($this->organization)->create();
    $this->settlement = ProviderSettlement::factory()->forProvider($provider)->create();
    $this->settlementLine = ProviderSettlementLine::factory()->forSettlement($this->settlement)->create();

    $this->urls = [
        '/api/v1/invoices',
        "/api/v1/invoices/{$this->invoice->id}/lines",
        '/api/v1/provider-settlements',
        "/api/v1/provider-settlements/{$this->settlement->id}/lines",
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
            ->postJson('/api/v1/invoices', [
                'customerId' => $this->invoice->customer_id,
                'invoiceNumber' => 'X', 'invoiceDate' => '2026-09-01',
                'currencyCode' => 'MAD', 'status' => 'draft',
                'lines' => [['lineNumber' => 1, 'description' => 'X', 'quantity' => 1, 'unitPrice' => 1, 'status' => 'x']],
            ])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/provider-settlements', [
                'providerId' => $this->settlement->provider_id,
                'settlementNumber' => 'X', 'status' => 'draft',
                'lines' => [['description' => 'X', 'quantity' => 1, 'unitCost' => 1]],
            ])->assertForbidden();
    });

    it('forbids updating and deleting without the matching permission', function (): void {
        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/invoices/{$this->invoice->id}", ['status' => 'x'])->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/invoices/{$this->invoice->id}/lines/{$this->line->id}")->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/provider-settlements/{$this->settlement->id}")->assertForbidden();

        $this->actingAs($this->powerless, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/provider-settlements/{$this->settlement->id}/lines/{$this->settlementLine->id}", ['quantity' => 2])
            ->assertForbidden();
    });

    it('grants read access once the view permissions are attached', function (): void {
        $role = Role::factory()->forOrganization($this->organization)->create();
        $permissions = Permission::whereIn('code', [
            'invoices.view', 'invoice_lines.view',
            'provider_settlements.view', 'provider_settlement_lines.view',
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
            ->deleteJson("/api/v1/invoices/{$this->invoice->id}")->assertForbidden();
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
