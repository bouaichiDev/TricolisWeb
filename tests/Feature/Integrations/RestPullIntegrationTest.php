<?php

use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Modules\Integrations\Exceptions\TransientIntegrationFailure;
use App\Modules\Integrations\Jobs\RunCustomerPullIntegrationJob;
use App\Modules\Integrations\Services\Pull\PullIntegrationRunner;
use App\Modules\Integrations\Services\Sources\RestPullConnections;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\CustomerIntegrations;

/**
 * Pull REST JSON horaire (§14-§18, §52) contre une API simulée.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerIntegrations::offlineDns();
    // Aucune requête ne sort : un appel que rien ne simule échoue le test.
    Http::preventStrayRequests();
    $this->configuration = CustomerIntegrations::import('ikea', 'json');
    $this->connection = app(RestPullConnections::class)->save($this->configuration->load('customer'), [
        'baseUrl' => 'https://api.partner.example.com',
        'authType' => 'bearer',
        'settings' => [
            'path' => '/v1/orders', 'recordsPath' => 'data', 'actorUserId' => authUser()->id,
            'since' => ['param' => 'updated_since'],
            'pagination' => ['type' => 'page', 'param' => 'page', 'sizeParam' => 'per_page', 'size' => 2],
        ],
    ], true, 'jeton-api-tres-secret');
    $this->runner = app(PullIntegrationRunner::class);
});

it('relève toutes les pages et crée des commandes REST_API', function (): void {
    Http::fake(['api.partner.example.com/v1/orders*' => static fn (Request $request) => Http::response(['data' => str_contains($request->url(), 'page=1')
        ? [CustomerIntegrations::apiOrder('SC-1'), CustomerIntegrations::apiOrder('SC-2')]
        : [CustomerIntegrations::apiOrder('SC-3')]])]);

    $summary = $this->runner->run($this->configuration);

    expect($summary->counts()['created'])->toBe(3)
        ->and(Order::where('external_reference', 'SC-3')->sole()->source->value)->toBe(OrderSource::REST_API->value)
        ->and($this->connection->fresh()->last_used_at)->not->toBeNull();
    Http::assertSent(static fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer jeton-api-tres-secret') && str_contains($request->url(), 'per_page=2'));
});

it('ignore au pull suivant une référence déjà importée, et filtre par date du dernier pull', function (): void {
    Http::fake(['*' => Http::response(['data' => [CustomerIntegrations::apiOrder('SC-10')]])]);
    $this->runner->run($this->configuration);
    $summary = $this->runner->run($this->configuration->fresh());

    expect($summary->counts())->toMatchArray(['created' => 0, 'skipped' => 1])
        ->and(Order::where('external_reference', 'SC-10')->count())->toBe(1);
    Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), 'updated_since='));
});

it('classe 500, 429 et un délai dépassé comme passagers', function (mixed $response): void {
    Http::fake(['*' => $response]);

    expect(fn () => $this->runner->run($this->configuration))->toThrow(TransientIntegrationFailure::class);
})->with([
    '500' => fn () => Http::response('boom', 500),
    '429' => fn () => Http::response('slow down', 429),
    'délai' => fn () => static fn () => throw new ConnectionException('cURL error 28: timeout'),
]);

it('classe 401, 403, une réponse non JSON ou invalide comme définitives', function (mixed $response, string $message): void {
    Http::fake(['*' => $response]);

    expect(fn () => $this->runner->run($this->configuration))->toThrow(PermanentIntegrationFailure::class, $message);
})->with([
    '401' => [fn () => Http::response('', 401), '401'],
    '403' => [fn () => Http::response('', 403), '403'],
    'html' => [fn () => Http::response('<html/>', 200, ['Content-Type' => 'text/html']), 'pas du JSON'],
    'json cassé' => [fn () => Http::response('{"data": [', 200, ['Content-Type' => 'application/json']), 'JSON valide'],
    'redirection' => [fn () => Http::response('', 302, ['Location' => 'http://169.254.169.254/']), 'redirection'],
]);

it('refuse une base interne sans l’appeler (SSRF)', function (): void {
    Http::fake();
    $this->connection->forceFill(['base_url' => 'https://169.254.169.254'])->save();

    expect(fn () => $this->runner->run($this->configuration))->toThrow(PermanentIntegrationFailure::class, 'interne');
    Http::assertNothingSent();
});

it('rejoue une erreur passagère par la file, et échoue sans boucler sur une définitive', function (): void {
    $job = new RunCustomerPullIntegrationJob($this->configuration->id);
    Http::fake(['*' => Http::sequence()->push('', 503)->push('', 401)]);

    expect(fn () => $job->handle($this->runner))->toThrow(TransientIntegrationFailure::class)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([60, 300]);

    $job = (new RunCustomerPullIntegrationJob($this->configuration->id))->withFakeQueueInteractions();
    $job->handle($this->runner);

    $job->assertFailed();
});

it('ne journalise jamais le jeton ni le corps de la réponse', function (): void {
    Log::spy();
    Http::fake(['*' => Http::response('{"error": "jeton-api-tres-secret invalide"}', 401, ['Content-Type' => 'application/json'])]);

    (new RunCustomerPullIntegrationJob($this->configuration->id))->withFakeQueueInteractions()->handle($this->runner);

    Log::shouldHaveReceived('error')->with('customer-integration.pull.failed', Mockery::on(
        static fn (array $context): bool => ! str_contains(json_encode($context), 'jeton-api-tres-secret'),
    ));
    expect(json_encode($this->connection->fresh()->toArray()))->not->toContain('jeton-api-tres-secret');
});
