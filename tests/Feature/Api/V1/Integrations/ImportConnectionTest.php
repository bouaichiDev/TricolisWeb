<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Jobs\RunCustomerPullIntegrationJob;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CustomerIntegrations;

/**
 * Le back-office des imports (§35, §48-§49) : champs cibles tenus par le
 * backend, connexion REST au secret en écriture seule, test réel, passe
 * manuelle, correspondance contrôlée à l'enregistrement.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerIntegrations::offlineDns();
    Http::preventStrayRequests();
    $this->configuration = CustomerIntegrations::import('ikea', 'json');
    $this->api = fn () => $this->actingAs(authUser(), 'sanctum')->withHeaders(['X-Organization-Id' => authOrganization()->id]);
    $this->base = "/api/v1/customer-import-configurations/{$this->configuration->id}";
    $this->connection = ['baseUrl' => 'https://api.partner.example.com', 'authType' => 'bearer', 'credentials' => 'jeton-pull-secret',
        'settings' => ['path' => '/v1/orders', 'recordsPath' => 'data', 'healthPath' => '/health', 'actorUserId' => authUser()->id]];
});

it('sert la liste contrôlée des champs cibles', function (): void {
    $response = ($this->api)()->getJson('/api/v1/customer-import-configurations/target-fields')->assertOk();

    expect(collect($response->json('data.groups'))->pluck('key')->all())->toContain('order', 'lines', 'services', 'recipient')
        ->and($response->json('data.types'))->toContain('decimal', 'date')
        ->and($response->json('data.validationRules'))->not->toContain('regex');
});

it('dit ce qui manque au lieu d’un faux « connexion OK »', function (): void {
    $response = ($this->api)()->getJson("{$this->base}/connection")->assertOk();

    expect($response->json('data.channel'))->toBe('rest_pull')
        ->and($response->json('data.flags.connectionMissing'))->toBeTrue()
        ->and($response->json('data.flags.testable'))->toBeFalse()
        ->and($response->json('data.ready'))->toBeFalse();
});

it('enregistre la connexion, chiffre le secret et ne le rend jamais', function (): void {
    $response = ($this->api)()->putJson("{$this->base}/connection", $this->connection)->assertOk();
    $stored = OrganizationApiConfiguration::where('code', 'customer-import-'.$this->configuration->id)->sole();

    expect($response->json('data.connection.hasCredentials'))->toBeTrue()
        ->and($response->json('data.ready'))->toBeTrue()
        ->and($response->getContent())->not->toContain('jeton-pull-secret')
        ->and($stored->credentials())->toBe('jeton-pull-secret')
        ->and($stored->getRawOriginal('encrypted_credentials'))->not->toContain('jeton-pull-secret');
    expect(json_encode(AuditLog::where('entity_id', $this->configuration->id)->pluck('new_values')))->not->toContain('jeton-pull-secret');

    // Sans `credentials`, le secret est gardé ; `null` l'efface.
    $withoutSecret = $this->connection;
    unset($withoutSecret['credentials']);
    ($this->api)()->putJson("{$this->base}/connection", [...$withoutSecret, 'baseUrl' => 'https://api2.partner.example.com'])->assertOk();
    expect($stored->fresh()->credentials())->toBe('jeton-pull-secret');

    ($this->api)()->putJson("{$this->base}/connection", [...$this->connection, 'credentials' => null])->assertOk();
    expect($stored->fresh()->hasCredentials())->toBeFalse();
});

it('refuse une URL non HTTPS et un en-tête Authorization en clair', function (): void {
    ($this->api)()->putJson("{$this->base}/connection", [...$this->connection, 'baseUrl' => 'http://api.partner.example.com'])->assertJsonValidationErrors('baseUrl');
    ($this->api)()->putJson("{$this->base}/connection", [...$this->connection, 'headers' => ['Authorization' => 'Bearer x']])->assertJsonValidationErrors('headers');
});

it('teste réellement la connexion, sans rien importer', function (): void {
    ($this->api)()->putJson("{$this->base}/connection", $this->connection)->assertOk();
    Http::fake(['api.partner.example.com/health' => Http::sequence()->push(['ok' => true])->push('', 401)]);

    ($this->api)()->postJson("{$this->base}/connection/test")->assertOk()->assertJsonPath('data.ok', true)->assertJsonPath('data.details.httpStatus', 200);
    Http::assertSentCount(1);

    ($this->api)()->postJson("{$this->base}/connection/test")->assertOk()->assertJsonPath('data.ok', false);
});

it('lance une passe manuelle en file pour une configuration prête', function (): void {
    Queue::fake();
    ($this->api)()->postJson("{$this->base}/run")->assertUnprocessable();

    ($this->api)()->putJson("{$this->base}/connection", $this->connection)->assertOk();
    ($this->api)()->postJson("{$this->base}/run")->assertStatus(202);

    Queue::assertPushed(RunCustomerPullIntegrationJob::class, fn ($job): bool => $job->configurationId === $this->configuration->id);
});

it('refuse à l’enregistrement une correspondance ou des règles que le moteur ne sait pas appliquer', function (): void {
    ($this->api)()->patchJson($this->base, ['mapping' => ['orderDate' => ['@source' => 'D', '@type' => 'eval']]])->assertJsonValidationErrors('mapping');
    ($this->api)()->patchJson($this->base, ['validationRules' => ['externalReference' => 'regex:/(a+)+$/']])->assertJsonValidationErrors('validationRules');
    ($this->api)()->patchJson($this->base, ['validationRules' => ['externalReference' => 'required|max:64', '@onDuplicate' => 'update']])->assertOk();
});
