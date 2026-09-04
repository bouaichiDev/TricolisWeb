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
        // L'e-mail exige en revanche un destinataire : c'est ce qui remplace
        // l'hote, pas une exigence en moins.
        $extra = ['email' => ['settings' => ['recipients' => 'compta@client.example']], 'manual' => []];

        foreach ($extra as $transport => $fields) {
            $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
                ->postJson('/api/v1/customer-export-configurations', ($this->payload)(array_merge([
                    'name' => "Export {$transport}",
                    'transport' => $transport,
                ], $fields)))
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

/**
 * Les réglages que la plateforme sait interpréter.
 *
 * `settings` reste ouvert — le §66 en fait le sac où chaque client range ses
 * conventions — mais les clés que le code lit vraiment sont vérifiées à la
 * saisie. Une URL de jeton vide ou un séparateur de trois caractères ne se
 * découvriraient sinon qu'à la première clôture, la facture déjà figée.
 */
describe('export settings', function (): void {
    it('accepts the documented authentication settings', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'transport' => 'rest_api',
                'format' => 'json',
                'host' => 'https://api.client.example',
                'settings' => [
                    'authType' => 'oauth2',
                    'tokenUrl' => 'https://auth.client.example/token',
                    'clientId' => 'tricolis',
                    'scope' => 'invoices.write',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.settings.authType', 'oauth2');
    });

    it('refuses an unknown authentication mode', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'settings' => ['authType' => 'kerberos'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('settings');
    });

    it('refuses a token url that is not a url', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'settings' => ['authType' => 'oauth2', 'tokenUrl' => 'pas-une-url'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('settings');
    });

    it('refuses a header name that is not a header name', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'settings' => ['authType' => 'api_key', 'apiKeyHeader' => 'X Api Key: bonus'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('settings');
    });

    it('refuses a csv delimiter longer than one character', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'settings' => ['delimiter' => '||'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('settings');
    });

    it('refuses an email destination without recipients', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'transport' => 'email',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('settings');
    });

    it('refuses a recipient that is not an address', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'transport' => 'email',
                'settings' => ['recipients' => 'compta@client.example, pas-une-adresse'],
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('settings');
    });

    /**
     * Le sac reste ouvert, et rien n'en tombe.
     *
     * Des règles imbriquées — `settings.authType` — amèneraient `validated()`
     * à ne rendre que les clés déclarées : le mapping du client disparaîtrait à
     * la première sauvegarde, sans un mot.
     */
    it('leaves unknown settings keys alone', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/customer-export-configurations', ($this->payload)([
                'settings' => ['fieldMapping' => ['invoiceNumber' => 'numero'], 'maisonTropRare' => true],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.settings.maisonTropRare', true)
            ->assertJsonPath('data.settings.fieldMapping.invoiceNumber', 'numero');
    });
});
