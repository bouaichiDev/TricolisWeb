<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

describe('addresses', function (): void {
    it('lists addresses for the active organization', function (): void {
        $address = Address::factory()->create();
        EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $address->id,
            'entity_type' => 'organization',
            'entity_id' => $this->organization->id,
        ]);

        Address::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson('/api/v1/addresses');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('creates an address linked to the active organization', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson('/api/v1/addresses', [
                'addressLine1' => '123 Rue de Paris',
                'city' => 'Paris',
                'country' => 'FR',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.addressLine1', '123 Rue de Paris')
            ->assertJsonPath('data.city', 'Paris');
    });

    it('prevents creating an address without organization header', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/addresses', [
                'addressLine1' => '123 Rue de Paris',
                'city' => 'Paris',
                'country' => 'FR',
            ]);

        $response->assertForbidden();
    });

    it('rejects invalid address payload', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson('/api/v1/addresses', [
                'country' => 'FRA',
                'latitude' => 999,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['addressLine1', 'country', 'latitude']);
    });

    it('shows an address belonging to the active organization', function (): void {
        $address = Address::factory()->create();
        EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $address->id,
            'entity_type' => 'organization',
            'entity_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/addresses/{$address->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $address->id);
    });

    it('prevents showing an address from another organization', function (): void {
        $address = Address::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/addresses/{$address->id}");

        $response->assertForbidden();
    });

    it('updates an address', function (): void {
        $address = Address::factory()->create();
        EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $address->id,
            'entity_type' => 'organization',
            'entity_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->patchJson("/api/v1/addresses/{$address->id}", [
                'city' => 'Lyon',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.city', 'Lyon');
    });

    it('deletes an address', function (): void {
        $address = Address::factory()->create();
        EntityAddress::create([
            'organization_id' => $this->organization->id,
            'address_id' => $address->id,
            'entity_type' => 'organization',
            'entity_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->deleteJson("/api/v1/addresses/{$address->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    });
});
