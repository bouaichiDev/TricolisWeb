<?php

use App\Modules\Exports\Models\ExportJob;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use App\Shared\Support\AuditContext;
use App\Shared\Support\Secret;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CustomerIntegrations;
use Tests\Support\CustomerPortal;

/**
 * REST Push du statut de commande (§19-§24, §53) : événement métier →
 * ExportJob → envoi JSON après commit, relance contrôlée.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerIntegrations::offlineDns();
    Http::preventStrayRequests();
    $this->ikea = CustomerPortal::customer('ikea');
    $this->configuration = CustomerIntegrations::statusPush($this->ikea, [], ['encrypted_password' => Secret::encrypt('jeton-push-tres-secret')]);
    $this->order = Order::where('customer_id', $this->ikea->id)->orderBy('id')->firstOrFail();
    $this->confirm = fn () => app(ChangeOrderStatus::class)->execute($this->order->fresh(), OrderStatus::CONFIRMED, authUser());
    $this->jobOf = fn (): ExportJob => ExportJob::where('entity_type', MorphMap::ORDER)->where('entity_id', $this->order->id)->sole();
    $this->internal = fn () => $this->actingAs(authUser(), 'sanctum')->withHeaders(['X-Organization-Id' => authOrganization()->id]);
});

it('pousse le statut en JSON vers l’hôte autorisé et date l’envoi', function (): void {
    Http::fake(['partner.example.com/*' => Http::response('', 202)]);

    ($this->confirm)();
    $job = ($this->jobOf)();

    expect($job->status)->toBe('sent')
        ->and($job->sent_at)->not->toBeNull()
        ->and($job->generated_at)->not->toBeNull()
        ->and($job->attempt_count)->toBe(1);

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://partner.example.com/tricolis/status'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer jeton-push-tres-secret')
            && $body['reference'] === $this->order->order_number
            && $body['status'] === 'confirmed'
            && array_keys($body['services'][0]) === ['serviceReference', 'sequence', 'status', 'requestedDate']
            && ! array_key_exists('id', $body) && ! array_key_exists('customerId', $body);
    });
});

it('renomme les champs selon la configuration, sans rien ajouter d’autre', function (): void {
    $this->configuration->update(['settings' => [...$this->configuration->settings, 'fieldMapping' => ['reference' => 'orderRef', 'services.status' => 'state'], 'includeServices' => true, 'staticValues' => ['carrier' => 'Tricolis']]]);
    Http::fake(['partner.example.com/*' => Http::response('', 200)]);

    ($this->confirm)();

    Http::assertSent(static fn (Request $request): bool => isset($request['orderRef'], $request['services'][0]['state']) && $request['carrier'] === 'Tricolis' && ! isset($request['reference']));
});

it('enregistre l’échec sans relancer par défaut, et relance les erreurs passagères si demandé', function (): void {
    Http::fake(['partner.example.com/*' => Http::response('{"token":"jeton-push-tres-secret"}', 500)]);
    ($this->confirm)();
    $job = ($this->jobOf)();

    expect($job->status)->toBe('failed')
        ->and($job->attempt_count)->toBe(1)
        ->and($job->error_message)->toBe('Le système du client a répondu 500.');

    $job->delete();
    $this->configuration->update(['settings' => [...$this->configuration->settings, 'retry' => ['maxAttempts' => 3, 'backoffSeconds' => 10]]]);
    Order::whereKey($this->order->id)->update(['status' => OrderStatus::DRAFT->value]);
    ($this->confirm)();

    expect(($this->jobOf)()->attempt_count)->toBe(3);
    Http::assertSentCount(4);
});

it('ne relance pas automatiquement une erreur 4xx', function (): void {
    $this->configuration->update(['settings' => [...$this->configuration->settings, 'retry' => ['maxAttempts' => 3]]]);
    Http::fake(['partner.example.com/*' => Http::response('', 400)]);

    ($this->confirm)();

    expect(($this->jobOf)()->attempt_count)->toBe(1);
});

it('ne crée pas un second envoi tant que le premier attend', function (): void {
    Queue::fake();
    ($this->confirm)();
    // Un second changement, avant que le premier envoi ne parte.
    OrderStatusChanged::dispatch($this->order->fresh(), OrderStatus::CONFIRMED, OrderStatus::CONFIRMED, new AuditContext(authOrganization()->id));

    expect(ExportJob::where('entity_id', $this->order->id)->count())->toBe(1);
});

it('n’envoie rien pour la commande d’un autre client', function (): void {
    Queue::fake();
    $qoqaOrder = Order::where('customer_id', CustomerPortal::customer('qoqa')->id)->firstOrFail();

    app(ChangeOrderStatus::class)->execute($qoqaOrder, OrderStatus::CONFIRMED, authUser());

    expect(ExportJob::where('entity_id', $qoqaOrder->id)->exists())->toBeFalse();
});

it('relance un envoi en échec depuis le back-office, jamais un envoi transmis', function (): void {
    Http::fake(['partner.example.com/*' => Http::sequence()->push('', 503)->push('', 200)]);
    ($this->confirm)();
    $job = ($this->jobOf)();

    ($this->internal)()->postJson("/api/v1/export-jobs/{$job->id}/retry", ['status' => 'pending'])->assertOk();

    expect($job->fresh()->status)->toBe('sent')->and($job->fresh()->attempt_count)->toBe(2);
    ($this->internal)()->postJson("/api/v1/export-jobs/{$job->id}/retry", ['status' => 'pending'])->assertStatus(409);
});

it('refuse de relancer vers une configuration désactivée', function (): void {
    Http::fake(['partner.example.com/*' => Http::response('', 503)]);
    ($this->confirm)();
    $this->configuration->update(['is_active' => false]);

    ($this->internal)()->postJson('/api/v1/export-jobs/'.($this->jobOf)()->id.'/retry', ['status' => 'pending'])
        ->assertStatus(409)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'désactivée'));
});

it('n’expose jamais le secret de la destination', function (): void {
    Http::fake(['partner.example.com/*' => Http::response('', 202)]);
    ($this->confirm)();

    $job = ($this->internal)()->getJson('/api/v1/export-jobs/'.($this->jobOf)()->id)->assertOk();
    $configuration = ($this->internal)()->getJson("/api/v1/customer-export-configurations/{$this->configuration->id}")->assertOk();

    expect($job->getContent().$configuration->getContent())->not->toContain('jeton-push-tres-secret')->not->toContain('encrypted');
});
