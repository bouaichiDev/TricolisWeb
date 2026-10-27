<?php

use App\Modules\Integrations\Exceptions\TransientIntegrationFailure;
use App\Modules\Integrations\Jobs\RunCustomerPullIntegrationJob;
use App\Modules\Integrations\Models\CustomerImportRun;
use App\Modules\Integrations\Services\Pull\PullIntegrationRunner;
use App\Modules\Integrations\Services\Sources\RestPullConnections;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Support\CustomerIntegrations;

/**
 * L'historique des relèves automatiques et du back-office (décision Phase 6
 * levée le 2026-10-10) : succès, refus, échecs — tous gardés.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerIntegrations::offlineDns();
    Http::preventStrayRequests();
    $this->api = fn () => $this->actingAs(authUser(), 'sanctum')->withHeaders(['X-Organization-Id' => authOrganization()->id]);
});

it('garde une relève REST réussie, sans auteur humain', function (): void {
    $configuration = CustomerIntegrations::import('ikea', 'json');
    app(RestPullConnections::class)->save($configuration->load('customer'), [
        'baseUrl' => 'https://api.partner.example.com', 'authType' => 'none',
        'settings' => ['path' => '/v1/orders', 'recordsPath' => 'data', 'actorUserId' => authUser()->id],
    ], false, null);
    Http::fake(['*' => Http::response(['data' => [CustomerIntegrations::apiOrder('SC-H1'), CustomerIntegrations::apiOrder('SC-H2')]])]);

    app(PullIntegrationRunner::class)->run($configuration);

    $run = CustomerImportRun::sole();
    expect($run->status)->toBe(CustomerImportRun::COMPLETED)
        ->and($run->channel)->toBe('pull')
        ->and($run->created_count)->toBe(2)
        ->and($run->triggered_by)->toBeNull()
        ->and($run->errors)->toBeNull();
});

it('garde une relève impossible comme échouée, avec sa raison', function (): void {
    $configuration = CustomerIntegrations::import('ikea', 'json');   // aucune connexion REST

    (new RunCustomerPullIntegrationJob($configuration->id))->withFakeQueueInteractions()->handle(app(PullIntegrationRunner::class));

    $run = CustomerImportRun::sole();
    expect($run->status)->toBe(CustomerImportRun::FAILED)
        ->and($run->error_message)->toContain('Aucune connexion REST');
});

it('garde une relève abandonnée après ses tentatives sur une erreur passagère', function (): void {
    $configuration = CustomerIntegrations::import('ikea', 'json');

    (new RunCustomerPullIntegrationJob($configuration->id))->failed(new TransientIntegrationFailure('Le service distant ne répond pas.'));

    expect(CustomerImportRun::sole()->error_message)->toContain('Relève abandonnée')->toContain('ne répond pas');
});

it('garde un fichier SFTP écarté comme échoué, sans bloquer les autres', function (): void {
    $configuration = CustomerIntegrations::import('ikea', 'xml');
    $disk = CustomerIntegrations::fakeSftp($configuration);
    $disk->put('outbox/broken.xml', '<orders><order>');
    $disk->put('outbox/good.xml', CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-H1', [['BILLY-80', 'Billy', '1']])));

    app(PullIntegrationRunner::class)->run($configuration->refresh());

    expect(CustomerImportRun::where('file_name', 'broken.xml')->sole()->status)->toBe(CustomerImportRun::FAILED)
        ->and(CustomerImportRun::where('file_name', 'good.xml')->sole()->status)->toBe(CustomerImportRun::COMPLETED);
});

it('sert l’historique d’une configuration au back-office, détail compris', function (): void {
    $configuration = CustomerIntegrations::import('ikea', 'json');
    $run = CustomerImportRun::create([
        'customer_id' => $configuration->customer_id, 'configuration_id' => $configuration->id, 'channel' => 'internal',
        'source' => 'json_import', 'status' => CustomerImportRun::REJECTED, 'invalid_count' => 1, 'triggered_by' => authUser()->id,
        'errors' => [['index' => 0, 'reference' => 'X-1', 'status' => 'invalid', 'errors' => ['order.date' => ['La date est obligatoire.']]]],
        'finished_at' => now(),
    ]);
    $base = "/api/v1/customer-import-configurations/{$configuration->id}/runs";

    ($this->api)()->getJson($base)->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', CustomerImportRun::REJECTED)
        ->assertJsonPath('data.0.triggeredBy.name', authUser()->fullName());

    ($this->api)()->getJson("{$base}/{$run->id}")->assertOk()->assertJsonPath('data.errors.0.reference', 'X-1');

    $other = CustomerIntegrations::import('qoqa', 'xlsx');
    ($this->api)()->getJson("/api/v1/customer-import-configurations/{$other->id}/runs/{$run->id}")->assertNotFound();
});
