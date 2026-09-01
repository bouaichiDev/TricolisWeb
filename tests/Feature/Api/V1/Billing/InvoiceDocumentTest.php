<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Customers\Models\Customer;
use App\Modules\Templates\Models\Template;

/**
 * Le document d'une facture : aperçu, résolution, immuabilité.
 *
 * Une facture close est un engagement. Le §0.22 exige qu'elle reste identique
 * après modification du modèle — sans quoi corriger une mention légale en
 * septembre réécrirait toutes les factures de l'année.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->invoice = function (): Invoice {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => 'draft',
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'order_service_id' => null,
            'description' => 'Livraison Genève',
        ]);

        return $invoice;
    };

    $this->template = fn (array $attributes = []): Template => Template::factory()->invoice()->create(array_merge([
        'organization_id' => $this->organization->id,
        'code' => 'INVOICE_DEFAULT',
        'body_template' => '<h1>Facture {{ invoice.invoiceNumber }}</h1>'
            .'{{#invoice.lines}}<p>{{ invoice.lines.description }}</p>{{/invoice.lines}}',
        'available_variables' => ['invoice.invoiceNumber', 'invoice.lines', 'invoice.lines.description'],
    ], $attributes));

    $this->document = fn (Invoice $invoice) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->getJson("/api/v1/invoices/{$invoice->id}/document");

    $this->close = fn (Invoice $invoice) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/invoices/{$invoice->id}/close");
});

describe('preview', function (): void {
    it('renders a draft from the resolved template', function (): void {
        ($this->template)();
        $invoice = ($this->invoice)();

        $response = ($this->document)($invoice)->assertOk();

        expect($response->json('data.html'))
            ->toContain($invoice->invoice_number)
            ->toContain('Livraison Genève')
            ->and($response->json('data.scope'))->toBe('global')
            ->and($response->json('data.isFrozen'))->toBeFalse();
    });

    it('names the customer template when one exists', function (): void {
        ($this->template)();
        ($this->template)(['code' => 'INVOICE_CUSTOMER', 'customer_id' => $this->customer->id]);

        $response = ($this->document)(($this->invoice)())->assertOk();

        expect($response->json('data.scope'))->toBe('customer')
            ->and($response->json('data.templateName'))->not->toBeNull();
    });

    /**
     * Aucune organisation existante n'a de modele au jour de la migration :
     * refuser ici aurait casse la facturation de tout le monde.
     */
    it('falls back to the delivered layout when no template exists', function (): void {
        $response = ($this->document)(($this->invoice)())->assertOk();

        expect($response->json('data.scope'))->toBe('fallback')
            ->and($response->json('data.templateId'))->toBeNull()
            ->and($response->json('data.html'))->toContain('Livraison Genève');
    });

    it('refuses an invoice of another organization', function (): void {
        $foreign = Invoice::factory()->create();

        ($this->document)($foreign)->assertNotFound();
    });
});

describe('immutability after closing', function (): void {
    it('freezes the document at closing time', function (): void {
        ($this->template)();
        $invoice = ($this->invoice)();

        ($this->close)($invoice)->assertOk();

        $invoice->refresh();

        expect($invoice->rendered_body)->toContain($invoice->invoice_number)
            ->and($invoice->rendered_at)->not->toBeNull()
            ->and($invoice->template_id)->not->toBeNull();
    });

    it('never re-renders a closed invoice with the current template', function (): void {
        $template = ($this->template)();
        $invoice = ($this->invoice)();

        ($this->close)($invoice)->assertOk();

        $before = ($this->document)($invoice)->json('data.html');

        $template->update(['body_template' => '<h1>VERSION B</h1>', 'available_variables' => []]);

        $after = ($this->document)($invoice)->json('data.html');

        expect($after)->toBe($before)
            ->and($after)->not->toContain('VERSION B');
    });

    it('marks the served document as frozen', function (): void {
        ($this->template)();
        $invoice = ($this->invoice)();

        ($this->close)($invoice)->assertOk();

        expect(($this->document)($invoice)->json('data.isFrozen'))->toBeTrue();
    });

    /**
     * Un modele ayant produit une facture fait partie de l'historique : le
     * supprimer laisserait une facture close sans explication de sa mise en page.
     */
    it('refuses to delete a template that produced an invoice', function (): void {
        $template = ($this->template)();

        ($this->close)(($this->invoice)())->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/templates/{$template->id}")
            ->assertStatus(409);
    });
});
