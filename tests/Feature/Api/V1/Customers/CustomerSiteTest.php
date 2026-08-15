<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerSite;
use App\Shared\Database\MorphMap;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::where('organization_id', $this->organization->id)->firstOrFail();
    $this->address = Address::factory()->create();
    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $this->address->id,
        'entity_type' => MorphMap::CUSTOMER,
        'entity_id' => $this->customer->id,
    ]);
});

describe('customer sites', function (): void {
    it('lists the sites of a customer', function (): void {
        CustomerSite::factory(2)->forCustomer($this->customer)->create(['address_id' => $this->address->id]);

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$this->customer->id}/sites")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('creates a site linked to an authorized address', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/sites", [
                'addressId' => $this->address->id,
                'code' => 'site-1',
                'name' => 'Entrepôt principal',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'site-1');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'created',
            'entity_type' => 'customer_site',
        ]);
    });

    it('rejects an address outside the active organization', function (): void {
        $foreignAddress = Address::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/sites", [
                'addressId' => $foreignAddress->id,
                'code' => 'site-2',
                'name' => 'Entrepôt interdit',
            ])
            ->assertNotFound();
    });

    it('updates and deletes a site', function (): void {
        $site = CustomerSite::factory()->forCustomer($this->customer)->create(['address_id' => $this->address->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/customers/{$this->customer->id}/sites/{$site->id}", ['name' => 'Site renommé'])
            ->assertOk()->assertJsonPath('data.name', 'Site renommé');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/customers/{$this->customer->id}/sites/{$site->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('customer_sites', ['id' => $site->id]);
    });

    it('refuses a site that belongs to another customer of the same organization', function (): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignSite = CustomerSite::factory()->forCustomer($otherCustomer)->create(['address_id' => $this->address->id]);

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$this->customer->id}/sites/{$foreignSite->id}")
            ->assertNotFound();
    });

    it('refuses listing the sites of a customer from another organization', function (): void {
        $foreignCustomer = Customer::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$foreignCustomer->id}/sites")
            ->assertNotFound();
    });
});
