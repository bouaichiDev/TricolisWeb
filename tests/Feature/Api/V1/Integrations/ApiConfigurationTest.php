<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerApiConfiguration;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->payload = fn (array $o = []): array => array_merge([
        'customerId' => $this->customer->id,
        'name' => 'Portail client',
    ], $o);
});

describe('api key issuance', function (): void {
    it('returns the key once and stores only its hash', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)())
            ->assertCreated();

        $key = $response->json('data.apiKey');
        $id = $response->json('data.configuration.id');

        expect($key)->toBeString()->toHaveLength(64)
            ->and($response->json('data.warning'))->toContain('une seule fois');

        // Seule l'empreinte est stockee.
        $this->assertDatabaseHas('customer_api_configurations', [
            'id' => $id,
            'api_key_hash' => hash('sha256', $key),
        ]);
        $this->assertDatabaseMissing('customer_api_configurations', ['api_key_hash' => $key]);
    });

    it('never exposes the hash on read', function (): void {
        $configuration = CustomerApiConfiguration::factory()->forCustomer($this->customer)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customer-api-configurations/{$configuration->id}")->assertOk();

        expect(array_keys($response->json('data')))
            ->not->toContain('apiKeyHash')
            ->not->toContain('api_key_hash')
            ->not->toContain('apiKey');
    });

    it('never exposes the hash on list', function (): void {
        CustomerApiConfiguration::factory()->forCustomer($this->customer)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/customer-api-configurations')->assertOk();

        expect(array_keys($response->json('data.0')))->not->toContain('apiKeyHash');
    });

    it('ignores a key supplied by the caller', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)([
                'apiKey' => 'cle-choisie-par-l-appelant',
                'apiKeyHash' => hash('sha256', 'cle-choisie-par-l-appelant'),
            ]))
            ->assertCreated();

        expect($response->json('data.apiKey'))->not->toBe('cle-choisie-par-l-appelant');
        $this->assertDatabaseMissing('customer_api_configurations', [
            'api_key_hash' => hash('sha256', 'cle-choisie-par-l-appelant'),
        ]);
    });

    it('does not journal the key in the audit log', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)())->assertCreated();

        $key = $response->json('data.apiKey');
        $logs = AuditLog::where('entity_id', $response->json('data.configuration.id'))->get();

        foreach ($logs as $log) {
            expect(json_encode([$log->old_values, $log->new_values]))
                ->not->toContain($key)
                ->not->toContain(hash('sha256', $key));
        }
    });
});

describe('api key rotation', function (): void {
    it('replaces the key and invalidates the previous one', function (): void {
        $created = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)())->assertCreated();

        $id = $created->json('data.configuration.id');
        $oldKey = $created->json('data.apiKey');

        $rotated = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customer-api-configurations/$id/rotate-key")->assertOk();

        $newKey = $rotated->json('data.apiKey');

        expect($newKey)->not->toBe($oldKey);

        $this->assertDatabaseHas('customer_api_configurations', [
            'id' => $id, 'api_key_hash' => hash('sha256', $newKey),
        ]);
        // L'ancienne empreinte a disparu : aucune trace, aucun historique.
        $this->assertDatabaseMissing('customer_api_configurations', [
            'api_key_hash' => hash('sha256', $oldKey),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer_api_configuration.key_rotated', 'entity_id' => $id,
        ]);
    });
});

describe('api configuration validation', function (): void {
    it('refuses a customer from another organization', function (): void {
        $foreign = Customer::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)(['customerId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('customerId');
    });

    it('accepts IP addresses and CIDR blocks', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)([
                'allowedIps' => ['192.168.1.10', '10.0.0.0/8', '2001:db8::1'],
            ]))
            ->assertCreated();
    });

    it('refuses a malformed ip entry', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)([
                'allowedIps' => ['pas-une-ip'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('allowedIps.0');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)([
                'allowedIps' => ['10.0.0.0/99'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('allowedIps.0');
    });

    it('accepts a known permission code', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)([
                'permissions' => ['orders.view', 'orders.create'],
            ]))
            ->assertCreated();
    });

    it('refuses an unknown permission and an administrative one', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)([
                'permissions' => ['orders.teleport'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('permissions.0');

        // Une cle client ne gere pas les comptes du transporteur.
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)([
                'permissions' => ['users.create'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('permissions.0');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)([
                'permissions' => ['roles.assign_permissions'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('permissions.0');
    });

    it('refuses a duplicated name for the same customer', function (): void {
        CustomerApiConfiguration::factory()->forCustomer($this->customer)->create(['name' => 'Portail client']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-api-configurations', ($this->payload)())
            ->assertStatus(422)->assertJsonValidationErrors('name');
    });
});

describe('api configuration crud and scope', function (): void {
    it('updates and deletes', function (): void {
        $configuration = CustomerApiConfiguration::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/customer-api-configurations/{$configuration->id}", ['isActive' => false])
            ->assertOk()->assertJsonPath('data.isActive', false);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/customer-api-configurations/{$configuration->id}")->assertNoContent();

        $this->assertDatabaseMissing('customer_api_configurations', ['id' => $configuration->id]);
    });

    it('hides a configuration from another organization', function (): void {
        $foreign = CustomerApiConfiguration::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customer-api-configurations/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customer-api-configurations/{$foreign->id}/rotate-key")->assertNotFound();
    });

    it('creates and lists through the customer route', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$this->customer->id}/api-configurations", ['name' => 'EDI'])
            ->assertCreated()->assertJsonPath('data.configuration.customerId', $this->customer->id);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$this->customer->id}/api-configurations")
            ->assertOk()->assertJsonCount(1, 'data');
    });
});
