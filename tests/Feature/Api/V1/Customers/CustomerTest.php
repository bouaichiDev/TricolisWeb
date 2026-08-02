<?php

use App\Modules\Customers\Models\Customer;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
});

describe('customers', function (): void {
    it('lists customers for the active organization', function (): void {
        Customer::factory(2)->create(['organization_id' => $this->organization->id]);
        Customer::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson('/api/v1/customers');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('creates a customer', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson('/api/v1/customers', [
                'code' => 'new-customer',
                'name' => 'New Customer',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'new-customer');
    });

    it('searches customers with MySQL-compatible matching', function (): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Atlas Special Transport',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson('/api/v1/customers?search=Special')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Atlas Special Transport');
    });

    it('returns a compact payload for dropdown lists', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson('/api/v1/customers?compact=1');

        $response->assertOk();

        expect(array_keys($response->json('data.0')))->not->toContain('catalogEnabled');
    });

    it('rejects duplicate code within the same organization', function (): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'code' => 'duplicate']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->postJson('/api/v1/customers', [
                'code' => 'duplicate',
                'name' => 'Duplicate Customer',
            ]);

        $response->assertUnprocessable();
    });

    it('prevents accessing a customer from another organization', function (): void {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertNotFound();
    });
});
