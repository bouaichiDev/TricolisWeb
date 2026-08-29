<?php

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Models\InvoiceLineAddressSnapshot;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    $this->invoice = Invoice::factory()->forCustomer($this->customer)->create();
    InvoiceLine::factory()->forInvoice($this->invoice)->atLine(1)->create();
    $this->url = "/api/v1/invoices/{$this->invoice->id}/lines";

    $this->order = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $this->customer->id]);
    $this->service = OrderService::factory()->create(['order_id' => $this->order->id]);

    $this->payload = fn (array $o = []): array => array_merge([
        'lineNumber' => 2,
        'description' => 'Prestation complémentaire',
        'quantity' => 1,
        'unitPrice' => 50,
        'status' => 'billable',
    ], $o);
});

describe('invoice lines creation', function (): void {
    /**
     * Aucun barème n'existe ici : le prix soumis est assumé, ce qui se
     * déclare. Sans ce choix, le §169AJ refuse la ligne plutôt que de la
     * facturer au hasard.
     */
    it('creates a line linked to a service of the invoiced customer', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)([
                'orderServiceId' => $this->service->id,
                'orderId' => $this->order->id,
                'priceOverride' => true,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.orderServiceId', $this->service->id);
    });

    it('refuses a service belonging to another customer', function (): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $otherOrder = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $otherCustomer->id]);
        $foreignService = OrderService::factory()->create(['order_id' => $otherOrder->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['orderServiceId' => $foreignService->id]))
            ->assertStatus(422)->assertJsonValidationErrors('orderServiceId');
    });

    it('refuses an order that is not the one of the billed service', function (): void {
        $otherOrder = Order::factory()->forOrganization($this->organization)->create(['customer_id' => $this->customer->id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)([
                'orderServiceId' => $this->service->id,
                'orderId' => $otherOrder->id,
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('orderId');
    });

    it('refuses a duplicated line number in the invoice', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['lineNumber' => 1]))
            ->assertStatus(422)->assertJsonValidationErrors('lineNumber');
    });

    it('refuses to bill the same service twice', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)([
                'orderServiceId' => $this->service->id,
                'priceOverride' => true,
            ]))
            ->assertCreated();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['lineNumber' => 3, 'orderServiceId' => $this->service->id]))
            ->assertStatus(422)->assertJsonValidationErrors('orderServiceId');
    });

    it('refuses a negative quantity and an out of range rate', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['quantity' => -1]))
            ->assertStatus(422)->assertJsonValidationErrors('quantity');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['taxRate' => 101]))
            ->assertStatus(422)->assertJsonValidationErrors('taxRate');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['discountRate' => -5]))
            ->assertStatus(422)->assertJsonValidationErrors('discountRate');
    });
});

describe('invoice line address snapshot', function (): void {
    it('creates an optional snapshot with the line', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)([
                'addressSnapshot' => [
                    'name' => 'IKEA Casablanca',
                    'addressLine1' => '12 boulevard Zerktouni',
                    'city' => 'Casablanca',
                    'country' => 'MA',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.addressSnapshot.city', 'Casablanca');

        $this->assertDatabaseHas('invoice_line_address_snapshots', [
            'invoice_line_id' => $response->json('data.id'),
            'name' => 'IKEA Casablanca',
        ]);
    });

    it('creates a line without any snapshot', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)())->assertCreated();

        $this->assertDatabaseMissing('invoice_line_address_snapshots', [
            'invoice_line_id' => $response->json('data.id'),
        ]);
    });

    it('keeps at most one snapshot per line', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)([
                'addressSnapshot' => ['city' => 'Rabat'],
            ]))->assertCreated();

        expect(
            InvoiceLineAddressSnapshot::where('invoice_line_id', $response->json('data.id'))->count()
        )->toBe(1);
    });

    it('disappears with its line', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)(['addressSnapshot' => ['city' => 'Fès']]))
            ->assertCreated();
        $lineId = $response->json('data.id');

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/$lineId")->assertNoContent();

        $this->assertDatabaseMissing('invoice_line_address_snapshots', ['invoice_line_id' => $lineId]);
    });
});

describe('invoice lines update and delete', function (): void {
    it('recalculates the line and the invoice on update', function (): void {
        $line = InvoiceLine::factory()->forInvoice($this->invoice)->atLine(5)->create([
            'quantity' => 1, 'unit_price' => 100, 'total_excluding_tax' => 100, 'total_including_tax' => 100,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/{$line->id}", ['quantity' => 3, 'taxRate' => 10])
            ->assertOk()
            ->assertJsonPath('data.totalExcludingTax', '300.00')
            ->assertJsonPath('data.totalIncludingTax', '330.00');

        // La premiere ligne du beforeEach vaut 100 HT / 100 TTC.
        expect($this->invoice->fresh()->subtotal)->toBe('400.00')
            ->and($this->invoice->fresh()->total)->toBe('430.00');
    });

    it('refuses to remove the last line', function (): void {
        $only = $this->invoice->lines()->firstOrFail();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/{$only->id}")->assertStatus(409);

        $this->assertDatabaseHas('invoice_lines', ['id' => $only->id]);
    });

    it('removes a line when another one remains', function (): void {
        $second = InvoiceLine::factory()->forInvoice($this->invoice)->atLine(9)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/{$second->id}")->assertNoContent();

        $this->assertDatabaseMissing('invoice_lines', ['id' => $second->id]);
    });
});

describe('invoice lines scope and audit', function (): void {
    it('hides a line of another invoice', function (): void {
        $other = Invoice::factory()->forCustomer($this->customer)->create();
        $line = InvoiceLine::factory()->forInvoice($other)->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("{$this->url}/{$line->id}")->assertNotFound();
    });

    it('hides the lines of an invoice from another organization', function (): void {
        $foreign = Invoice::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/invoices/{$foreign->id}/lines")->assertNotFound();
    });

    it('audits creation, update and deletion', function (): void {
        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->url, ($this->payload)())->assertCreated();
        $id = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invoice_line.created', 'entity_type' => 'invoice_line', 'entity_id' => $id,
        ]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->patchJson("{$this->url}/$id", ['description' => 'Modifié'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice_line.updated', 'entity_id' => $id]);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("{$this->url}/$id")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice_line.deleted', 'entity_id' => $id]);
    });
});
