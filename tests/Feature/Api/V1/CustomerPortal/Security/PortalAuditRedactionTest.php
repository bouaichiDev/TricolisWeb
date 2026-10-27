<?php

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Shared\Logging\RedactSensitiveContext;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Log\Logger;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Audit et journaux (Phase 7, §22-§26) : l'action sensible est tracée dans
 * `AuditLog` avec son auteur, son organisation et son entité — et aucun
 * secret n'y entre, ni dans les journaux applicatifs.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
});

it('trace une commande créée dans le portail : organisation, auteur, entité', function (): void {
    $order = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->postJson('/api/v1/customer/orders', CustomerPortalOrders::payload())
        ->assertCreated()
        ->json('data.id');
    $member = CustomerPortal::member('admin@ikea.test');
    $log = AuditLog::where('action', 'customer_portal.order_created')->where('entity_id', $order)->sole();

    expect($log->organization_id)->toBe($member->customer->organization_id)
        ->and($log->user_id)->toBe($member->user_id)
        ->and($log->entity_type)->toBe('order')
        ->and($log->new_values)->toHaveKey('orderNumber');
});

it('n’écrit jamais un secret dans l’audit, à aucune profondeur', function (): void {
    $log = app(WriteAuditLog::class)->execute(authOrganization()->id, authUser(), 'test.redaction', CustomerPortal::member('admin@ikea.test'), null, [
        'name' => 'visible',
        'password' => 'p', 'apiKeyHash' => 'h', 'encrypted_password' => 'e', 'Authorization' => 'Bearer x',
        'settings' => ['clientSecret' => 's', 'headers' => ['X-Api-Key' => 'k'], 'tokenUrl' => 'https://auth.example.com'],
        'credentials' => 'c', 'remember_token' => 't', 'cookie' => 'c',
    ]);
    $values = $log->fresh()->new_values;

    expect($values['name'])->toBe('visible')
        ->and($values['settings']['tokenUrl'])->toBe('https://auth.example.com')
        ->and(json_encode($values))->not->toContain('Bearer x')
        ->and(collect(['password', 'apiKeyHash', 'encrypted_password', 'Authorization', 'credentials', 'remember_token', 'cookie'])->map(fn (string $key) => $values[$key])->unique()->all())->toBe(['[REDACTED]'])
        ->and($values['settings']['clientSecret'])->toBe('[REDACTED]')
        ->and($values['settings']['headers']['X-Api-Key'])->toBe('[REDACTED]');
});

it('n’audite ni la clé API ni son empreinte à la création d’un accès', function (): void {
    $response = $this->actingAs(authUser(), 'sanctum')->withHeaders(['X-Organization-Id' => authOrganization()->id])
        ->postJson('/api/v1/customer-api-configurations', ['customerId' => CustomerPortal::customer('ikea')->id, 'name' => 'ERP audit', 'permissions' => ['orders.view']])
        ->assertCreated();
    $key = (string) $response->json('data.apiKey');
    $hash = CustomerApiConfiguration::where('name', 'ERP audit')->value('api_key_hash');
    $audit = AuditLog::where('entity_type', 'customer_api_configuration')->get()->toJson();

    expect($key)->not->toBe('')
        ->and($audit)->not->toContain($key)
        ->and($audit)->not->toContain((string) $hash)
        ->and($response->getContent())->not->toContain((string) $hash);
});

it('masque les secrets du contexte d’une ligne de journal', function (): void {
    $handler = new TestHandler;
    $logger = new Logger(new Monolog('test', [$handler]));
    (new RedactSensitiveContext)($logger);

    $logger->warning('appel refusé', ['configurationId' => '01J', 'apiKey' => 'cle-claire', 'request' => ['headers' => ['authorization' => 'Bearer jeton']]]);

    $context = $handler->getRecords()[0]->context;
    expect($context['configurationId'])->toBe('01J')
        ->and($context['apiKey'])->toBe('[REDACTED]')
        ->and($context['request']['headers']['authorization'])->toBe('[REDACTED]');
});
