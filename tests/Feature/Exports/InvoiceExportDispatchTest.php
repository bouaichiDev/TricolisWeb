<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Models\InvoiceLineAddressSnapshot;
use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Mail\InvoiceExportMail;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Exports\Services\ExportDispatcher;
use App\Shared\Database\MorphMap;
use App\Shared\Support\Secret;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * L'envoi d'une facture chez le client.
 *
 * Deux exigences dominent : ne jamais transmettre ce qui n'est pas clôturé
 * (§88), et ne jamais laisser fuir le secret d'accès (§124). Le reste — format,
 * nom de fichier, reprise — sert l'exploitant, pas la sécurité.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->dispatcher = app(ExportDispatcher::class);

    Storage::fake('local');

    $this->invoice = function (string $status = 'closed', array $o = []): Invoice {
        $invoice = Invoice::factory()->forCustomer($this->customer)->create(array_merge([
            'status' => $status,
            'invoice_number' => 'INV-2026-00042',
            'currency_code' => 'CHF',
            'total' => '108.10',
        ], $o));

        $line = InvoiceLine::factory()->forInvoice($invoice)->atLine(1)->create([
            'description' => 'Livraison Genève & retour',
            'service_code' => 'DEL',
            'total_including_tax' => '108.10',
        ]);

        InvoiceLineAddressSnapshot::factory()->forLine($line)->create();

        return $invoice;
    };

    $this->rest = fn (array $o = []): CustomerExportConfiguration => CustomerExportConfiguration::factory()
        ->forCustomer($this->customer)
        ->create(array_merge([
            'export_type' => 'invoice',
            'frequency' => 'on_invoice_closed',
            'transport' => 'rest_api',
            'format' => 'json',
            'host' => 'https://facturation.client.example',
            'encrypted_password' => Secret::encrypt('jeton-tres-secret'),
            'settings' => ['endpointPath' => 'v1/invoices'],
        ], $o));

    $this->job = fn (CustomerExportConfiguration $c, Invoice $i): ExportJob => ExportJob::factory()
        ->forConfiguration($c)
        ->create(['entity_type' => MorphMap::INVOICE, 'entity_id' => $i->id]);
});

describe('transmission REST', function (): void {
    it('dépose la facture et marque l’envoi', function (): void {
        Http::fake(['*' => Http::response('', 202)]);

        $job = ($this->job)(($this->rest)(), ($this->invoice)());

        $this->dispatcher->process($job);

        expect($job->fresh()->status)->toBe('sent')
            ->and($job->fresh()->sent_at)->not->toBeNull();

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://facturation.client.example/v1/invoices'
                && $body['invoiceNumber'] === 'INV-2026-00042'
                // §65 : un montant part en chaine, sinon il derive a la relecture.
                && $body['total'] === '108.10';
        });
    });

    /** §72 : le secret passe par le champ chiffré, jamais par un réglage lisible. */
    it('porte le jeton chiffré et ignore un Authorization déclaré', function (): void {
        Http::fake(['*' => Http::response('', 200)]);

        $configuration = ($this->rest)(['settings' => [
            'endpointPath' => 'v1/invoices',
            'headers' => ['Authorization' => 'Bearer usurpe', 'X-Canal' => 'tricolis'],
        ]]);

        $this->dispatcher->process(($this->job)($configuration, ($this->invoice)()));

        Http::assertSent(fn ($request): bool => $request->header('Authorization') === ['Bearer jeton-tres-secret']
            && $request->header('X-Canal') === ['tricolis']);
    });

    /** §27 et §147 : une API en panne n'échoue pas le travail, elle se reprend. */
    it('consigne l’échec sans le faire remonter', function (): void {
        Http::fake(['*' => Http::response('', 503)]);

        $job = ($this->job)(($this->rest)(), ($this->invoice)());

        $this->dispatcher->process($job);

        expect($job->fresh()->status)->toBe('failed')
            ->and($job->fresh()->attempt_count)->toBe(1)
            ->and($job->fresh()->error_message)->toContain('503');
    });

    /** §124 : ni le corps de la réponse, ni le jeton, ne sont conservés. */
    it('ne laisse pas le secret dans le message d’erreur', function (): void {
        Http::fake(['*' => Http::response('jeton-tres-secret refusé', 401)]);

        $job = ($this->job)(($this->rest)(), ($this->invoice)());

        $this->dispatcher->process($job);

        expect($job->fresh()->error_message)->not->toContain('jeton-tres-secret');
    });

    /** §70 : la méthode ne se choisit pas librement. */
    it('refuse une méthode HTTP arbitraire', function (): void {
        Http::fake();

        $configuration = ($this->rest)(['settings' => ['method' => 'DELETE']]);
        $job = ($this->job)($configuration, ($this->invoice)());

        $this->dispatcher->process($job);

        expect($job->fresh()->status)->toBe('failed');
        Http::assertNothingSent();
    });

    /** §125 : notre serveur ne sera pas l'outil d'un balayage interne. */
    it('refuse une destination interne', function (): void {
        Http::fake();

        $configuration = ($this->rest)(['host' => 'http://169.254.169.254']);
        $job = ($this->job)($configuration, ($this->invoice)());

        $this->dispatcher->process($job);

        expect($job->fresh()->status)->toBe('failed')
            ->and($job->fresh()->error_message)->toContain('interne');

        Http::assertNothingSent();
    });
});

describe('garde-fous', function (): void {
    /** §88 : même un job forgé ne transmet pas un brouillon. */
    it('refuse de transmettre une facture non clôturée', function (): void {
        Http::fake();

        $job = ($this->job)(($this->rest)(), ($this->invoice)('draft'));

        $this->dispatcher->process($job);

        expect($job->fresh()->status)->toBe('failed');
        Http::assertNothingSent();
    });

    /** §114 : la facture d'un client ne part pas chez un autre. */
    it('refuse une destination d’un autre client', function (): void {
        Http::fake();

        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $configuration = ($this->rest)(['customer_id' => $other->id]);

        $job = ExportJob::factory()->forConfiguration($configuration)->create([
            'entity_type' => MorphMap::INVOICE,
            'entity_id' => ($this->invoice)()->id,
        ]);

        $this->dispatcher->process($job);

        expect($job->fresh()->status)->toBe('failed');
        Http::assertNothingSent();
    });

    it('refuse une destination désactivée', function (): void {
        Http::fake();

        $job = ($this->job)(($this->rest)(['is_active' => false]), ($this->invoice)());

        $this->dispatcher->process($job);

        expect($job->fresh()->status)->toBe('failed');
        Http::assertNothingSent();
    });

    /**
     * §32 : un format proposé sans générateur créerait une destination qui
     * échoue à chaque clôture, loin de l'écran qui l'a acceptée. Le cas ne se
     * teste plus par un refus — les quatre formats sont produits — mais en
     * vérifiant qu'aucun n'est resté sur le bord.
     */
    it('sait produire chacun des formats du modèle', function (): void {
        Http::fake(['*' => Http::response('', 200)]);

        // Une seule facture : son numero est unique par organisation, et ce
        // qu'on eprouve ici est le format, pas la facturation.
        $invoice = ($this->invoice)();

        foreach (ExportFormat::cases() as $format) {
            $job = ($this->job)(($this->rest)(['format' => $format->value]), $invoice);

            $this->dispatcher->process($job);

            expect($job->fresh()->status)->toBe('sent', $format->value);
        }
    });
});

describe('fichier produit', function (): void {
    it('conserve le fichier envoyé et son nom', function (): void {
        Http::fake(['*' => Http::response('', 200)]);

        $configuration = ($this->rest)([
            'file_name_pattern' => '{invoiceNumber}_{currencyCode}',
            'settings' => ['endpointPath' => 'v1/invoices'],
        ]);

        $job = ($this->job)($configuration, ($this->invoice)());

        $this->dispatcher->process($job);

        $job->refresh();

        expect($job->file_name)->toBe('INV-2026-00042_CHF.json');

        Storage::disk('local')->assertExists($job->storage_path);
    });

    /** §81 : un gabarit n'est pas une expression, et ne remonte pas d'un cran. */
    it('aplatit un gabarit qui tente de sortir du dossier', function (): void {
        Http::fake(['*' => Http::response('', 200)]);

        $configuration = ($this->rest)([
            'file_name_pattern' => '../../etc/{invoiceNumber}',
            'settings' => ['endpointPath' => 'v1/invoices'],
        ]);

        $job = ($this->job)($configuration, ($this->invoice)());

        $this->dispatcher->process($job);

        expect($job->fresh()->file_name)->not->toContain('/')
            ->and($job->fresh()->file_name)->not->toContain('..');
    });
});

/**
 * Les quatre formats et les cinq transports du modèle vont jusqu'au bout.
 *
 * Le §32 n'autorise à proposer un format que si son générateur existe : ces cas
 * vérifient qu'aucune destination configurable depuis l'écran n'échoue à la
 * clôture, ce qui serait découvert bien trop tard.
 */
describe('formats et transports', function (): void {
    it('produit un CSV et le dépose', function (): void {
        Http::fake(['*' => Http::response('', 200)]);

        $job = ($this->job)(($this->rest)(['format' => 'csv']), ($this->invoice)());

        $this->dispatcher->process($job);

        $job->refresh();

        expect($job->status)->toBe('sent')
            ->and($job->file_name)->toEndWith('.csv')
            ->and(Storage::disk('local')->get($job->storage_path))->toContain('INV-2026-00042');
    });

    it('produit un PDF et le dépose', function (): void {
        Http::fake(['*' => Http::response('', 200)]);

        $job = ($this->job)(($this->rest)(['format' => 'pdf']), ($this->invoice)());

        $this->dispatcher->process($job);

        $job->refresh();

        expect($job->status)->toBe('sent')
            ->and($job->file_name)->toEndWith('.pdf')
            ->and(Storage::disk('local')->get($job->storage_path))->toStartWith('%PDF-');
    });

    it('envoie la facture par courriel', function (): void {
        Mail::fake();

        $configuration = ($this->rest)([
            'transport' => 'email',
            'format' => 'pdf',
            'host' => null,
            'settings' => ['recipients' => 'compta@client.example'],
        ]);

        $job = ($this->job)($configuration, ($this->invoice)());

        $this->dispatcher->process($job);

        Mail::assertSent(InvoiceExportMail::class);

        expect($job->fresh()->status)->toBe('sent');
    });

    /** Le mode manuel range le fichier et n'appelle personne. */
    it('range le fichier sans rien transmettre en mode manuel', function (): void {
        Http::fake();
        Mail::fake();

        $configuration = ($this->rest)([
            'transport' => 'manual',
            'format' => 'json',
            'host' => null,
            'settings' => [],
        ]);

        $job = ($this->job)($configuration, ($this->invoice)());

        $this->dispatcher->process($job);

        $job->refresh();

        Http::assertNothingSent();
        Mail::assertNothingSent();

        expect($job->status)->toBe('sent')
            ->and(Storage::disk('local')->exists($job->storage_path))->toBeTrue();
    });

    /** Une destination courriel sans adresse échoue en le disant (§27). */
    it('échoue proprement quand un envoi courriel n’a pas de destinataire', function (): void {
        Mail::fake();

        $configuration = ($this->rest)([
            'transport' => 'email',
            'host' => null,
            'settings' => [],
        ]);

        $job = ($this->job)($configuration, ($this->invoice)());

        $this->dispatcher->process($job);

        $job->refresh();

        Mail::assertNothingSent();

        expect($job->status)->toBe('failed')
            ->and($job->error_message)->toContain('destinataire');
    });
});
