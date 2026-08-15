<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->payload = fn (array $o = []): array => array_merge([
        'customerId' => $this->customer->id,
        'name' => 'Import commandes',
        'sourceType' => 'sftp',
        'fileFormat' => 'csv',
    ], $o);
});

describe('import configuration crud', function (): void {
    it('creates a configuration with a free-form mapping', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-import-configurations', ($this->payload)([
                'mapping' => ['orderNumber' => 'A', 'weight' => 'F'],
                'validationRules' => ['orderNumber' => 'required'],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.mapping.orderNumber', 'A');

        $this->assertDatabaseHas('customer_import_configurations', [
            'id' => $response->json('data.id'),
            'customer_id' => $this->customer->id,
        ]);
    });

    it('creates a configuration without any mapping', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-import-configurations', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.mapping', null)
            ->assertJsonPath('data.isActive', true);
    });

    it('refuses a non-array mapping', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-import-configurations', ($this->payload)([
                'mapping' => 'phpinfo();',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('mapping');
    });

    it('refuses a customer from another organization', function (): void {
        $foreign = Customer::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-import-configurations', ($this->payload)(['customerId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('customerId');
    });

    it('refuses a duplicated name for the same customer but allows it elsewhere', function (): void {
        CustomerImportConfiguration::factory()->forCustomer($this->customer)->create(['name' => 'Import commandes']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-import-configurations', ($this->payload)())
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/customers/{$other->id}/import-configurations", [
                'name' => 'Import commandes', 'sourceType' => 'sftp', 'fileFormat' => 'csv',
            ])
            ->assertCreated();
    });

    it('updates and deletes', function (): void {
        $configuration = CustomerImportConfiguration::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/customer-import-configurations/{$configuration->id}", ['isActive' => false])
            ->assertOk()->assertJsonPath('data.isActive', false);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/customer-import-configurations/{$configuration->id}")->assertNoContent();

        $this->assertDatabaseMissing('customer_import_configurations', ['id' => $configuration->id]);
    });

    it('hides a configuration from another organization', function (): void {
        $foreign = CustomerImportConfiguration::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customer-import-configurations/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/customer-import-configurations/{$foreign->id}")->assertNotFound();
    });

    it('lists, searches and filters', function (): void {
        CustomerImportConfiguration::factory()->forCustomer($this->customer)->create([
            'name' => 'ZZZ', 'source_type' => 'email', 'is_active' => false,
        ]);
        CustomerImportConfiguration::factory(2)->forCustomer($this->customer)->create();
        CustomerImportConfiguration::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/customer-import-configurations')->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/customer-import-configurations?search=ZZZ')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/customer-import-configurations?sourceType=email')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customers/{$this->customer->id}/import-configurations")
            ->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/customer-import-configurations?sort=customer_id')->assertStatus(422);
    });

    it('has no import execution tables', function (): void {
        expect(Schema::hasTable('imports'))->toBeFalse()
            ->and(Schema::hasTable('import_files'))->toBeFalse()
            ->and(Schema::hasTable('import_rows'))->toBeFalse()
            ->and(Schema::hasTable('import_errors'))->toBeFalse();

        $columns = Schema::getColumnListing('customer_import_configurations');
        expect($columns)->not->toContain('organization_id')
            ->and($columns)->not->toContain('created_at');
    });

    it('audits creation, update and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-import-configurations', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer_import_configuration.created',
            'entity_type' => 'customer_import_configuration',
            'entity_id' => $id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/customer-import-configurations/$id", ['sourceType' => 'ftp'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer_import_configuration.updated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/customer-import-configurations/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer_import_configuration.deleted', 'entity_id' => $id]);
    });
});
