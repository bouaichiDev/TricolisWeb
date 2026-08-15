<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\OrderService;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->line = fn (array $o = []): array => array_merge([
        'lineNumber' => 1,
        'description' => 'Transport Casablanca — Rabat',
        'quantity' => 1,
        'unitPrice' => 100,
        'status' => 'billable',
    ], $o);

    $this->payload = fn (array $o = [], ?array $lines = null): array => array_merge([
        'customerId' => $this->customer->id,
        'invoiceNumber' => 'INV-2026-0001',
        'invoiceDate' => '2026-09-30',
        'currencyCode' => 'MAD',
        'status' => 'draft',
        'lines' => $lines ?? [($this->line)()],
    ], $o);
});

describe('invoices creation', function (): void {
    it('creates an invoice with one line', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)())
            ->assertCreated()
            ->assertJsonPath('data.invoiceNumber', 'INV-2026-0001')
            ->assertJsonCount(1, 'data.lines');

        $this->assertDatabaseHas('invoices', [
            'id' => $response->json('data.id'),
            'organization_id' => $this->organization->id,
        ]);
    });

    it('refuses an invoice without any line', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)(['lines' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('lines');
    });

    it('writes nothing when a line references a foreign service', function (): void {
        $foreignService = OrderService::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)(lines: [
                ($this->line)(['orderServiceId' => $foreignService->id]),
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('lines.0.orderServiceId');

        // La creation est atomique : aucune facture orpheline ne subsiste.
        expect(Invoice::where('organization_id', $this->organization->id)->count())->toBe(0);
    });

    it('refuses a customer from another organization', function (): void {
        $foreign = Customer::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)(['customerId' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors('customerId');
    });

    it('refuses an inverted period and a missing currency', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)([
                'periodFrom' => '2026-09-30',
                'periodTo' => '2026-09-01',
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('periodTo');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)(['currencyCode' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('currencyCode');
    });

    it('refuses a duplicated invoice number in the organization', function (): void {
        Invoice::factory()->forCustomer($this->customer)->create(['invoice_number' => 'INV-DUP']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)(['invoiceNumber' => 'INV-DUP']))
            ->assertStatus(422)->assertJsonValidationErrors('invoiceNumber');
    });

    it('allows the same number in another organization', function (): void {
        Invoice::factory()->create(['invoice_number' => 'INV-DUP']);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)(['invoiceNumber' => 'INV-DUP']))
            ->assertCreated();
    });
});

describe('invoices totals', function (): void {
    it('computes line and invoice totals, ignoring any submitted total', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)(
                ['subtotal' => 99999, 'total' => 99999],
                [
                    ($this->line)(['lineNumber' => 1, 'quantity' => 2, 'unitPrice' => 100, 'taxRate' => 20]),
                    ($this->line)(['lineNumber' => 2, 'quantity' => 1, 'unitPrice' => 50, 'discountRate' => 10, 'taxRate' => 20]),
                ],
            ))
            ->assertCreated();

        // Ligne 1 : 200 HT, 240 TTC. Ligne 2 : 45 HT, 54 TTC.
        expect($response->json('data.subtotal'))->toBe('245.00')
            ->and($response->json('data.total'))->toBe('294.00')
            ->and($response->json('data.taxTotal'))->toBe('49.00');
    });

    it('keeps subtotal plus taxTotal equal to total after rounding', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)(lines: [
                ($this->line)(['lineNumber' => 1, 'quantity' => 3, 'unitPrice' => 33.33, 'taxRate' => 7.7]),
                ($this->line)(['lineNumber' => 2, 'quantity' => 7, 'unitPrice' => 1.11, 'taxRate' => 7.7]),
            ]))
            ->assertCreated();

        $subtotal = (float) $response->json('data.subtotal');
        $taxTotal = (float) $response->json('data.taxTotal');
        $total = (float) $response->json('data.total');

        expect(round($subtotal + $taxTotal, 2))->toBe(round($total, 2));
    });

    it('applies a discount before tax', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)(lines: [
                ($this->line)(['quantity' => 1, 'unitPrice' => 200, 'discountRate' => 25, 'taxRate' => 10]),
            ]))
            ->assertCreated();

        // 200 − 25 % = 150 HT ; + 10 % = 165 TTC.
        expect($response->json('data.lines.0.totalExcludingTax'))->toBe('150.00')
            ->and($response->json('data.lines.0.totalIncludingTax'))->toBe('165.00');
    });
});

describe('invoices schema', function (): void {
    it('has no legacyId nor invented billing columns', function (): void {
        $invoices = Schema::getColumnListing('invoices');
        $lines = Schema::getColumnListing('invoice_lines');

        expect($invoices)->not->toContain('legacy_id')
            ->and($invoices)->not->toContain('due_date')
            ->and($invoices)->not->toContain('paid_at')
            ->and($invoices)->not->toContain('payment_status')
            ->and($invoices)->not->toContain('updated_at')
            ->and($lines)->not->toContain('legacy_id')
            ->and($lines)->not->toContain('discount_amount')
            ->and($lines)->not->toContain('tax_amount')
            ->and($lines)->not->toContain('unit');

        expect(Schema::hasTable('payments'))->toBeFalse()
            ->and(Schema::hasTable('credit_notes'))->toBeFalse()
            ->and(Schema::hasTable('invoice_line_sources'))->toBeFalse();
    });
});

describe('invoices read, update and delete', function (): void {
    it('reads, updates and deletes an invoice', function (): void {
        $invoice = Invoice::factory()->forCustomer($this->customer)->create();
        InvoiceLine::factory()->forInvoice($invoice)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/invoices/{$invoice->id}")->assertOk()
            ->assertJsonPath('data.id', $invoice->id);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/invoices/{$invoice->id}", ['status' => 'issued'])
            ->assertOk()->assertJsonPath('data.status', 'issued');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/invoices/{$invoice->id}")->assertNoContent();

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoice_lines', ['invoice_id' => $invoice->id]);
    });

    it('hides an invoice from another organization', function (): void {
        $foreign = Invoice::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/invoices/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/invoices/{$foreign->id}", ['status' => 'x'])->assertNotFound();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/invoices/{$foreign->id}")->assertNotFound();
    });
});

describe('invoices list', function (): void {
    it('lists only the invoices of the active organization', function (): void {
        Invoice::factory(2)->forCustomer($this->customer)->create();
        Invoice::factory(3)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/invoices')->assertOk()->assertJsonCount(2, 'data');
    });

    it('searches and filters', function (): void {
        Invoice::factory()->forCustomer($this->customer)->create([
            'invoice_number' => 'ZZZ-1',
            'external_reference' => 'REF-ABC',
            'status' => 'issued',
        ]);
        Invoice::factory()->forCustomer($this->customer)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/invoices?search=ZZZ')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/invoices?search=REF-ABC')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/invoices?status=issued')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/invoices?customerId={$this->customer->id}")->assertOk()->assertJsonCount(2, 'data');
    });

    it('paginates, sorts and rejects a forbidden sort column', function (): void {
        Invoice::factory(5)->forCustomer($this->customer)->create();

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/invoices?perPage=2')->assertOk()->assertJsonCount(2, 'data');
        expect($response->json('meta.perPage'))->toBe(2);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/invoices?sort=total&direction=desc')->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/invoices?sort=organization_id')->assertStatus(422);
    });
});

describe('invoices audit', function (): void {
    it('audits creation, update, recalculation and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson('/api/v1/invoices', ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.created', 'entity_type' => 'invoice', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("/api/v1/invoices/$id", ['remark' => 'Relance'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.updated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson("/api/v1/invoices/$id/lines", ($this->line)(['lineNumber' => 2]))->assertCreated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice_totals.recalculated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/invoices/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.deleted', 'entity_id' => $id]);
    });
});
