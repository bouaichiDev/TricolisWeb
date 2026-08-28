<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Jobs\ProcessExportJob;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * La reprise d'un envoi manqué.
 *
 * Le §147 la veut possible sans reprendre la clôture : une API du client
 * indisponible une heure ne doit pas obliger à refaire la facture. La relance
 * remet donc le même envoi en file, avec le fichier régénéré à l'identique
 * depuis la facture — qui, elle, est figée.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    Storage::fake('local');

    $this->configuration = CustomerExportConfiguration::factory()->forCustomer($this->customer)->create([
        'export_type' => 'invoice',
        'frequency' => 'on_invoice_closed',
        'transport' => 'rest_api',
        'format' => 'json',
        'host' => 'https://facturation.client.example',
        'settings' => ['endpointPath' => 'v1/invoices'],
    ]);

    $this->closedInvoice = function (): Invoice {
        $invoice = Invoice::factory()->forCustomer($this->customer)->create(['status' => 'closed']);
        InvoiceLine::factory()->forInvoice($invoice)->atLine(1)->create();

        return $invoice;
    };

    $this->failedJob = fn (Invoice $invoice): ExportJob => ExportJob::factory()
        ->forConfiguration($this->configuration)
        ->failed('Le système du client a répondu 503.')
        ->create(['entity_type' => MorphMap::INVOICE, 'entity_id' => $invoice->id]);

    $this->retry = fn (ExportJob $job) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/export-jobs/{$job->id}/retry", ['status' => 'pending']);
});

it('remet l’envoi de facture en file', function (): void {
    Queue::fake();

    $job = ($this->failedJob)(($this->closedInvoice)());

    ($this->retry)($job)->assertOk()->assertJsonPath('data.errorMessage', null);

    Queue::assertPushed(ProcessExportJob::class);
});

/**
 * Le compteur mesure des tentatives d'envoi. L'avancer à la demande **et** au
 * renvoi le ferait compter double, et il ne dirait plus rien.
 */
it('laisse le renvoi compter sa propre tentative', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    $job = ($this->failedJob)(($this->closedInvoice)());

    expect($job->attempt_count)->toBe(1);

    ($this->retry)($job)->assertOk();

    expect($job->fresh()->attempt_count)->toBe(2)
        ->and($job->fresh()->status)->toBe('sent');
});

/** Le fichier est régénéré depuis une facture figée : il est identique. */
it('renvoie la même facture', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    $invoice = ($this->closedInvoice)();

    ($this->retry)(($this->failedJob)($invoice))->assertOk();

    Http::assertSent(fn ($request): bool => json_decode($request->body(), true)['invoiceNumber'] === $invoice->invoice_number);
});

/** §27 : un envoi déjà reçu ne se rejoue pas — le client aurait deux factures. */
it('refuse de renvoyer un envoi déjà transmis', function (): void {
    Queue::fake();

    $job = ExportJob::factory()->forConfiguration($this->configuration)->sent()->create([
        'entity_type' => MorphMap::INVOICE,
        'entity_id' => ($this->closedInvoice)()->id,
    ]);

    ($this->retry)($job)->assertStatus(409);

    Queue::assertNotPushed(ProcessExportJob::class);
});

/**
 * Un export dont personne ne génère le contenu — la demande manuelle de la
 * Phase 8 — se remet à zéro sans repartir : le rejouer échouerait aussitôt.
 */
it('ne remet pas en file un export sans facture', function (): void {
    Queue::fake();

    $job = ExportJob::factory()->forConfiguration($this->configuration)->failed()->create();

    ($this->retry)($job)->assertOk()->assertJsonPath('data.attemptCount', 2);

    Queue::assertNotPushed(ProcessExportJob::class);
});
