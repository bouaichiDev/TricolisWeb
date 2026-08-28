<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Jobs\ProcessExportJob;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Exports\Services\InvoiceExportTrigger;
use Illuminate\Support\Facades\Queue;

/**
 * La clôture d'une facture.
 *
 * C'est le seul déclencheur d'envoi — le §20 et le §21 l'exigent : une facture
 * au brouillon ne part chez personne, même si une destination active existe.
 * Elle fige aussi le document, que le client détiendra bientôt.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->invoice = function (string $status = 'draft', int $lines = 1): Invoice {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => $status,
        ]);

        for ($number = 1; $number <= $lines; $number++) {
            InvoiceLine::factory()->create([
                'invoice_id' => $invoice->id,
                'line_number' => $number,
                'order_service_id' => null,
            ]);
        }

        return $invoice;
    };

    $this->destination = fn (array $o = []): CustomerExportConfiguration => CustomerExportConfiguration::factory()
        ->create(array_merge([
            'customer_id' => $this->customer->id,
            'export_type' => InvoiceExportTrigger::EXPORT_TYPE,
            'frequency' => InvoiceExportTrigger::ON_CLOSED,
            'transport' => 'rest_api',
            'format' => 'json',
            'is_active' => true,
        ], $o));

    $this->close = fn (Invoice $invoice) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/invoices/{$invoice->id}/close");
});

describe('clôture', function (): void {
    it('fait passer la facture à « clôturée »', function (): void {
        $invoice = ($this->invoice)();

        ($this->close)($invoice)->assertOk()->assertJsonPath('data.invoice.status', 'closed');

        expect($invoice->fresh()->status)->toBe('closed');
    });

    /** §8 : une facture porte au moins une ligne. Clôturer un document vide
     *  enverrait au client une facture sans prestation. */
    it('refuse une facture sans ligne', function (): void {
        $invoice = ($this->invoice)('draft', 0);

        ($this->close)($invoice)->assertStatus(422)->assertJsonValidationErrors('status');

        expect($invoice->fresh()->status)->toBe('draft');
    });

    it('journalise la clôture', function (): void {
        ($this->close)(($this->invoice)())->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.closed']);
    });
});

describe('immutabilité', function (): void {
    /**
     * §22 : le document est peut-être déjà chez le client. Le contredire en
     * base laisserait deux vérités.
     */
    it('refuse de modifier une facture clôturée', function (): void {
        $invoice = ($this->invoice)('closed');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/invoices/{$invoice->id}", ['remark' => 'après coup'])
            ->assertStatus(422);
    });

    it('refuse de la supprimer', function (): void {
        $invoice = ($this->invoice)('closed');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    });

    it('refuse d’y ajouter une ligne', function (): void {
        $invoice = ($this->invoice)('closed');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/invoices/{$invoice->id}/lines", [
                'lineNumber' => 9,
                'description' => 'Ajout tardif',
                'quantity' => 1,
                'unitPrice' => 10,
                'status' => 'billable',
            ])
            ->assertStatus(422);
    });

    it('refuse d’en retirer une', function (): void {
        $invoice = ($this->invoice)('closed', 2);
        $line = $invoice->lines()->first();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/invoices/{$invoice->id}/lines/{$line->id}")
            ->assertStatus(422);
    });
});

describe('déclenchement des envois', function (): void {
    /** §21 : rien ne part tant que la facture n'est pas clôturée. */
    it('n’envoie rien tant que la facture est au brouillon', function (): void {
        Queue::fake();

        ($this->destination)();
        ($this->invoice)();

        expect(ExportJob::count())->toBe(0);

        Queue::assertNothingPushed();
    });

    it('crée un envoi par destination active', function (): void {
        Queue::fake();

        ($this->destination)(['name' => 'API du client']);
        ($this->destination)(['name' => 'Dépôt SFTP', 'transport' => 'sftp', 'format' => 'xml']);

        $invoice = ($this->invoice)();

        ($this->close)($invoice)->assertOk()->assertJsonCount(2, 'data.exportJobs');

        expect(ExportJob::where('entity_id', $invoice->id)->count())->toBe(2);

        Queue::assertPushed(ProcessExportJob::class, 2);
    });

    it('ignore une destination désactivée', function (): void {
        Queue::fake();

        ($this->destination)(['is_active' => false]);

        ($this->close)(($this->invoice)())->assertOk()->assertJsonCount(0, 'data.exportJobs');
    });

    /** §28 : un client sans intégration reste facturable. */
    it('clôture sans destination, et le dit', function (): void {
        Queue::fake();

        ($this->close)(($this->invoice)())->assertOk()->assertJsonCount(0, 'data.exportJobs');
    });

    /** §113 : la configuration d'un autre client ne sert jamais. */
    it('n’utilise pas la destination d’un autre client', function (): void {
        Queue::fake();

        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        ($this->destination)(['customer_id' => $other->id]);

        ($this->close)(($this->invoice)())->assertOk()->assertJsonCount(0, 'data.exportJobs');
    });

    /** §30 : deux clics ne font pas deux envois. */
    it('reste idempotente', function (): void {
        Queue::fake();

        ($this->destination)();
        $invoice = ($this->invoice)();

        ($this->close)($invoice)->assertOk();
        ($this->close)($invoice)->assertOk()->assertJsonCount(1, 'data.exportJobs');

        expect(ExportJob::where('entity_id', $invoice->id)->count())->toBe(1);
    });
});

describe('aperçu avant clôture', function (): void {
    /** §52 : savoir où la facture partira avant de la figer. */
    it('annonce les destinations', function (): void {
        ($this->destination)(['name' => 'API du client']);
        $invoice = ($this->invoice)();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/invoices/{$invoice->id}/closure")
            ->assertOk()
            ->assertJsonPath('data.closable', true)
            ->assertJsonPath('data.lineCount', 1)
            ->assertJsonPath('data.destinations.0.name', 'API du client');
    });

    it('dit qu’une facture vide n’est pas clôturable', function (): void {
        $invoice = ($this->invoice)('draft', 0);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/invoices/{$invoice->id}/closure")
            ->assertOk()
            ->assertJsonPath('data.closable', false);
    });
});

describe('la clôture ne se contourne pas', function (): void {
    /**
     * **Le trou que cette phase a ouvert.** `PATCH` acceptait n'importe quel
     * statut : poser `closed` à la main figeait la facture sans créer le
     * moindre envoi. Le client n'aurait rien reçu, et rien ne l'aurait dit.
     */
    it('refuse de clôturer par une mise à jour', function (): void {
        $invoice = ($this->invoice)();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/invoices/{$invoice->id}", ['status' => 'closed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        expect($invoice->fresh()->status)->toBe('draft');
    });

    /** Le référentiel gouverne les codes : un statut inventé n'entre pas. */
    it('refuse un statut absent du référentiel', function (): void {
        $invoice = ($this->invoice)();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/invoices/{$invoice->id}", ['status' => 'issued'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    });
});
