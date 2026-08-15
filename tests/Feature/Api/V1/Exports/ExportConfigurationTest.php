<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Shared\Support\Secret;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->payload = fn (array $o = []): array => array_merge([
        'customerId' => $this->customer->id,
        'name' => 'Export commandes',
        'exportType' => 'orders',
        'format' => 'csv',
        'transport' => 'manual',
    ], $o);
});

describe('export configuration creation', function (): void {
    it('creates a manual configuration without connection fields', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.transport', 'manual')
            ->assertJsonPath('data.hasPassword', false);
    });

    it('requires a host for ftp, sftp and rest_api', function (): void {
        foreach (['ftp', 'sftp', 'rest_api'] as $transport) {
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                    'name' => "Export {$transport}",
                    'transport' => $transport,
                ]))
                ->assertStatus(422)->assertJsonValidationErrors('host');
        }
    });

    it('does not require a host for email and manual', function (): void {
        foreach (['email', 'manual'] as $transport) {
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                    'name' => "Export {$transport}",
                    'transport' => $transport,
                ]))
                ->assertCreated();
        }
    });

    it('refuses a format or transport outside the enums', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)(['format' => 'xlsx']))
            ->assertStatus(422)->assertJsonValidationErrors('format');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)(['transport' => 's3']))
            ->assertStatus(422)->assertJsonValidationErrors('transport');
    });

    it('refuses a path traversal in the file name pattern', function (): void {
        foreach (['../etc/passwd', 'sous/dossier.csv', "nul\x00.csv"] as $pattern) {
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                    'fileNamePattern' => $pattern,
                ]))
                ->assertStatus(422)->assertJsonValidationErrors('fileNamePattern');
        }
    });

    it('accepts a plain file name pattern', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'fileNamePattern' => 'commandes-{date}.csv',
            ]))
            ->assertCreated()->assertJsonPath('data.fileNamePattern', 'commandes-{date}.csv');
    });

    it('refuses an out of range port', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'transport' => 'sftp', 'host' => 'sftp.example.test', 'port' => 99999,
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('port');
    });
});

describe('export configuration password', function (): void {
    it('encrypts the password and never returns it', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'transport' => 'sftp', 'host' => 'sftp.example.test',
                'username' => 'tricolis', 'password' => 'motdepasse-secret',
            ]))
            ->assertCreated()->assertJsonPath('data.hasPassword', true);

        $body = $response->getContent();
        expect($body)->not->toContain('motdepasse-secret')
            ->and(array_keys($response->json('data')))->not->toContain('encryptedPassword');

        $stored = CustomerExportConfiguration::find($response->json('data.id'));
        expect($stored->encrypted_password)->not->toBe('motdepasse-secret')
            ->and(Secret::decrypt($stored->encrypted_password))->toBe('motdepasse-secret');
    });

    it('keeps the password when the payload omits it', function (): void {
        $configuration = CustomerExportConfiguration::factory()->forCustomer($this->customer)->sftp()->create();
        $before = $configuration->encrypted_password;

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/customer-export-configurations/{$configuration->id}", ['username' => 'autre'])
            ->assertOk()->assertJsonPath('data.hasPassword', true);

        expect($configuration->fresh()->encrypted_password)->toBe($before);
    });

    it('clears the password when null is sent explicitly', function (): void {
        $configuration = CustomerExportConfiguration::factory()->forCustomer($this->customer)->sftp()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/customer-export-configurations/{$configuration->id}", ['password' => null])
            ->assertOk()->assertJsonPath('data.hasPassword', false);

        expect($configuration->fresh()->encrypted_password)->toBeNull();
    });

    it('never journals the password in the audit log', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'transport' => 'sftp', 'host' => 'h.test', 'password' => 'motdepasse-secret',
            ]))->assertCreated();

        $logs = AuditLog::where('entity_id', $response->json('data.id'))->get();

        foreach ($logs as $log) {
            expect(json_encode([$log->old_values, $log->new_values]))->not->toContain('motdepasse-secret');
        }
    });
});

describe('export configuration deletion and scope', function (): void {
    it('refuses to delete a configuration that produced exports', function (): void {
        $configuration = CustomerExportConfiguration::factory()->forCustomer($this->customer)->create();
        ExportJob::factory()->forConfiguration($configuration)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/customer-export-configurations/{$configuration->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('customer_export_configurations', ['id' => $configuration->id]);
    });

    it('deletes an unused configuration', function (): void {
        $configuration = CustomerExportConfiguration::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/customer-export-configurations/{$configuration->id}")->assertNoContent();
    });

    it('hides a configuration from another organization', function (): void {
        $foreign = CustomerExportConfiguration::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/customer-export-configurations/{$foreign->id}")->assertNotFound();
    });

    it('lists, searches and filters', function (): void {
        CustomerExportConfiguration::factory()->forCustomer($this->customer)->create([
            'name' => 'ZZZ', 'export_type' => 'invoices', 'is_active' => false,
        ]);
        CustomerExportConfiguration::factory(2)->forCustomer($this->customer)->create();
        CustomerExportConfiguration::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/customer-export-configurations')->assertOk()->assertJsonCount(3, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/customer-export-configurations?search=ZZZ')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/customer-export-configurations?isActive=0')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/customer-export-configurations?sort=customer_id')->assertStatus(422);
    });

    it('has no invented columns', function (): void {
        $columns = Schema::getColumnListing('customer_export_configurations');

        expect($columns)->not->toContain('organization_id')
            ->and($columns)->not->toContain('created_at')
            ->and($columns)->not->toContain('updated_at')
            ->and($columns)->not->toContain('api_key')
            ->and($columns)->not->toContain('next_run_at');

        expect(Schema::hasTable('webhooks'))->toBeFalse()
            ->and(Schema::hasTable('api_tokens'))->toBeFalse()
            ->and(Schema::hasTable('export_templates'))->toBeFalse();
    });
});
