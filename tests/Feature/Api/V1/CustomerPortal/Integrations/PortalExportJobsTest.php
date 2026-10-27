<?php

use App\Modules\Exports\Models\ExportJob;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Support\CustomerIntegrations;
use Tests\Support\CustomerPortal;

/**
 * Les envois du client dans le portail (§24, §38) : historique en lecture,
 * relance d'un échec par la même Action que le back-office.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    CustomerIntegrations::offlineDns();
    Http::preventStrayRequests();
    $ikea = CustomerPortal::customer('ikea');
    $this->push = CustomerIntegrations::statusPush($ikea);
    $this->order = Order::where('customer_id', $ikea->id)->orderBy('id')->firstOrFail();
    $this->job = ExportJob::create([
        'customer_id' => $ikea->id, 'configuration_id' => $this->push->id, 'entity_type' => MorphMap::ORDER,
        'entity_id' => $this->order->id, 'status' => 'failed', 'attempt_count' => 1,
        'error_message' => 'Le système du client a répondu 503.',
    ]);
});

it('liste les envois avec la référence lisible de la commande', function (): void {
    $response = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->getJson('/api/v1/customer/integrations/export-jobs?status=failed')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect($response->json('data.0.entityReference'))->toBe($this->order->order_number)
        ->and($response->json('data.0.errorMessage'))->toBe('Le système du client a répondu 503.')
        ->and($response->json('data.0.actions.canRetry'))->toBeTrue()
        ->and($response->json('data.0'))->not->toHaveKey('storagePath');
});

it('relance un envoi en échec : nouvelle tentative, sentAt renseigné', function (): void {
    Http::fake(['partner.example.com/*' => Http::response('', 200)]);

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->postJson("/api/v1/customer/integrations/export-jobs/{$this->job->id}/retry")
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.attemptCount', 2);

    $this->assertDatabaseHas('audit_logs', ['action' => 'customer_portal.integration_export_job_retried', 'entity_id' => $this->job->id]);
});

it('refuse de relancer un envoi déjà transmis', function (): void {
    $this->job->update(['status' => 'sent', 'sent_at' => now()]);

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->postJson("/api/v1/customer/integrations/export-jobs/{$this->job->id}/retry")
        ->assertStatus(409);
});

it('réserve la relance à la permission dédiée', function (): void {
    $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))
        ->postJson("/api/v1/customer/integrations/export-jobs/{$this->job->id}/retry")
        ->assertForbidden();

    expect($this->job->fresh()->attempt_count)->toBe(1);
});

it('n’offre aucune modification ni suppression de l’historique', function (string $method): void {
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))
        ->json($method, "/api/v1/customer/integrations/export-jobs/{$this->job->id}")
        ->assertStatus(405);
})->with(['PATCH', 'PUT', 'DELETE']);
