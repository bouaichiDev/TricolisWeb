<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Pull\PullIntegrationRunner;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\CustomerIntegrations;

/**
 * La connexion FTP/SFTP d'une relève, réglée dans le back-office (décision
 * Phase 6 levée le 2026-10-10) : mot de passe chiffré, en écriture seule,
 * jamais rendu ni audité ; hôte interne et remontée de chemin refusés.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerIntegrations::offlineDns(['sftp.internal.example.com' => ['10.0.0.5']]);
    $this->configuration = CustomerIntegrations::import('ikea', 'xml');
    $this->api = fn () => $this->actingAs(authUser(), 'sanctum')->withHeaders(['X-Organization-Id' => authOrganization()->id]);
    $this->url = "/api/v1/customer-import-configurations/{$this->configuration->id}/connection";
    $this->payload = ['host' => 'sftp.partner.example.com', 'port' => 22, 'username' => 'tricolis', 'password' => 'mot-de-passe-sftp-secret',
        'directory' => 'outbox', 'filePattern' => '*.xml', 'afterImport' => 'archive', 'archiveDirectory' => 'outbox/archive', 'actorEmail' => 'admin@tricolis.dev'];
});

it('enregistre la connexion, chiffre le mot de passe et ne le rend jamais', function (): void {
    $response = ($this->api)()->putJson($this->url, $this->payload)->assertOk();

    expect($response->json('data.connection.configured'))->toBeTrue()
        ->and($response->json('data.connection.host'))->toBe('sftp.partner.example.com')
        ->and($response->json('data.connection.driver'))->toBe('sftp')
        ->and($response->json('data.connection.hasCredentials'))->toBeTrue()
        ->and($response->json('data.flags.connectionMissing'))->toBeFalse()
        ->and($response->getContent())->not->toContain('mot-de-passe-sftp-secret');

    $stored = DB::table('customer_import_configurations')->where('id', $this->configuration->id)->first();
    expect($stored->encrypted_password)->not->toBeNull()->not->toContain('mot-de-passe-sftp-secret')
        ->and($stored->connection)->not->toContain('mot-de-passe')
        ->and(CustomerImportConfiguration::find($this->configuration->id)->toArray())->not->toHaveKey('encrypted_password');

    $audit = AuditLog::where('action', 'customer_import_configuration.connection_saved')->latest('created_at')->firstOrFail();
    expect(json_encode($audit->new_values))->not->toContain('mot-de-passe')
        ->and($audit->new_values['accessChanged'])->toBeTrue();
});

it('garde le mot de passe quand le champ est absent, l’efface quand il est nul', function (): void {
    ($this->api)()->putJson($this->url, $this->payload)->assertOk();

    $kept = ($this->api)()->putJson($this->url, [...array_diff_key($this->payload, ['password' => true]), 'directory' => 'inbox'])->assertOk();
    expect($kept->json('data.connection.hasCredentials'))->toBeTrue()
        ->and($kept->json('data.connection.directory'))->toBe('inbox');

    $cleared = ($this->api)()->putJson($this->url, [...$this->payload, 'password' => null])->assertOk();
    expect($cleared->json('data.connection.hasCredentials'))->toBeFalse()
        ->and($cleared->json('data.flags.credentialMissing'))->toBeTrue();
});

it('refuse un hôte interne, une remontée de chemin, un motif qui est un chemin', function (array $override, string $field): void {
    ($this->api)()->putJson($this->url, [...$this->payload, ...$override])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(CustomerImportConfiguration::find($this->configuration->id)->connection)->toBeNull();
})->with([
    'hôte interne' => [['host' => 'sftp.internal.example.com'], 'host'],
    'hôte avec protocole' => [['host' => 'sftp://sftp.partner.example.com'], 'host'],
    'remontée' => [['directory' => '../../etc'], 'directory'],
    'motif-chemin' => [['filePattern' => 'outbox/*.xml'], 'filePattern'],
    'auteur inconnu' => [['actorEmail' => 'personne@example.test'], 'actorEmail'],
]);

it('n’accepte pas de connexion sur un import par dépôt de fichier', function (): void {
    $upload = CustomerIntegrations::import('qoqa', 'xlsx');

    ($this->api)()->putJson("/api/v1/customer-import-configurations/{$upload->id}/connection", $this->payload)->assertUnprocessable();
});

it('relève avec la connexion enregistrée', function (): void {
    // Le disque simulé d'abord, puis la connexion telle que l'API l'enregistre.
    $disk = CustomerIntegrations::fakeSftp($this->configuration, ['host' => 'ignored.example.com']);
    ($this->api)()->putJson($this->url, $this->payload)->assertOk();
    $disk->put('outbox/orders_901.xml', CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-901', [['BILLY-80', 'Billy', '1']])));

    $summary = app(PullIntegrationRunner::class)->run($this->configuration->refresh());

    expect($summary->counts()['created'])->toBe(1)
        ->and($this->configuration->refresh()->connection['host'])->toBe('sftp.partner.example.com');
});
