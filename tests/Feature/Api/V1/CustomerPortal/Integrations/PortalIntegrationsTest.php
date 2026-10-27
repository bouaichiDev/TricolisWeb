<?php

use App\Modules\Exports\Models\ExportJob;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Services\ApiKeyGenerator;
use App\Shared\Database\MorphMap;
use App\Shared\Support\Secret;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerIntegrations;
use Tests\Support\CustomerPortal;

/**
 * Le module Intégrations du portail (§36-§39) : le client voit ses flux, et
 * rien de ce qui permettrait de s'y connecter.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $ikea = CustomerPortal::customer('ikea');
    $this->ikeaImport = CustomerIntegrations::import('ikea', 'xlsx', ['name' => 'Commandes Excel IKEA']);
    $this->ikeaPush = CustomerIntegrations::statusPush($ikea, ['headers' => ['X-Tenant' => 'en-tete-secret']], [
        'username' => 'compte-technique', 'encrypted_password' => Secret::encrypt('mot-de-passe-push'),
    ]);
    CustomerApiConfiguration::factory()->forCustomer($ikea)->create([
        'name' => 'ERP IKEA', 'api_key_hash' => app(ApiKeyGenerator::class)->hash('cle-api-en-clair'),
        'permissions' => ['orders.view'], 'allowed_ips' => ['203.0.113.0/24'],
    ]);
    $this->qoqaImport = CustomerIntegrations::import('qoqa', 'xlsx', ['name' => 'Commandes Excel QOQA']);
});

it('liste les intégrations du client, avec leur état et leur mode', function (): void {
    $response = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson('/api/v1/customer/integrations')->assertOk();

    expect(collect($response->json('data.imports'))->pluck('name')->all())->toBe(['Commandes Excel IKEA'])
        ->and($response->json('data.imports.0.frequency'))->toBe('manual')
        ->and($response->json('data.imports.0.actions.canUpload'))->toBeTrue()
        ->and($response->json('data.exports.0.transport'))->toBe('rest_api')
        ->and($response->json('data.apiAccesses.0.restrictedByIp'))->toBeTrue();
});

it('n’expose aucun secret, aucune connexion, aucune empreinte de clé', function (): void {
    $headers = CustomerPortal::headers('admin@ikea.test');
    $body = $this->withHeaders($headers)->getJson('/api/v1/customer/integrations')->getContent()
        .$this->withHeaders($headers)->getJson('/api/v1/customer/integrations/export-configurations')->getContent()
        .$this->withHeaders($headers)->getJson('/api/v1/customer/integrations/api-configurations')->getContent();

    foreach (['mot-de-passe-push', 'compte-technique', 'partner.example.com', 'en-tete-secret', 'apiKeyHash', 'api_key_hash', 'encrypted', 'password', 'settings', '203.0.113', 'cle-api-en-clair'] as $secret) {
        expect($body)->not->toContain($secret);
    }
});

it('ne montre jamais les intégrations d’un autre client', function (): void {
    $response = $this->withHeaders(CustomerPortal::headers('admin@qoqa.test'))->getJson('/api/v1/customer/integrations')->assertOk();

    expect(collect($response->json('data.imports'))->pluck('name')->all())->toBe(['Commandes Excel QOQA'])
        ->and($response->json('data.exports'))->toBe([])
        ->and($response->json('data.apiAccesses'))->toBe([]);
});

it('exige la permission de voir les intégrations', function (): void {
    $this->withHeaders(CustomerPortal::headers('viewer@ikea.test'))->getJson('/api/v1/customer/integrations')->assertForbidden();
});

it('refuse un identifiant de client fourni dans la requête', function (): void {
    $qoqa = CustomerPortal::customer('qoqa');

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson("/api/v1/customer/integrations/export-jobs?customerId={$qoqa->id}")
        ->assertUnprocessable();
});

it('ne montre la correspondance qu’avec la permission dédiée', function (): void {
    $admin = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson('/api/v1/customer/integrations/import-configurations');
    $orders = $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))->getJson('/api/v1/customer/integrations/import-configurations');

    expect($admin->json('data.0.mapping'))->toContain(['target' => 'externalReference', 'source' => 'Commande', 'constant' => null, 'type' => null])
        ->and($orders->json('data.0.mapping'))->toBeNull();
});

it('répond 404 sur l’envoi d’un autre client (IDOR)', function (): void {
    $qoqaPush = CustomerIntegrations::statusPush(CustomerPortal::customer('qoqa'));
    $foreign = ExportJob::create([
        'customer_id' => $qoqaPush->customer_id, 'configuration_id' => $qoqaPush->id, 'entity_type' => MorphMap::ORDER,
        'entity_id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ', 'status' => 'failed', 'attempt_count' => 1,
    ]);
    $headers = CustomerPortal::headers('admin@ikea.test');

    $this->withHeaders($headers)->getJson("/api/v1/customer/integrations/export-jobs/{$foreign->id}")->assertNotFound();
    $this->withHeaders($headers)->postJson("/api/v1/customer/integrations/export-jobs/{$foreign->id}/retry")->assertNotFound();
    $this->withHeaders($headers)->getJson('/api/v1/customer/integrations/export-jobs/pas-un-ulid')->assertNotFound();

    expect($foreign->fresh()->status)->toBe('failed');
});
