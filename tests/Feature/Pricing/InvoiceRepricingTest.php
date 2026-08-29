<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\Service;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceRule;

/**
 * Recalculer les prix d'un brouillon.
 *
 * Le §169AM le veut explicite, et l'écart visible avant d'être écrit : une
 * facture qui bouge en silence ne se contrôle plus.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->delivery = Service::factory()->create([
        'organization_id' => $this->organization->id,
        'code' => 'DEL',
        'name' => 'Livraison',
    ]);

    $this->rule = function (string $formula): PriceRule {
        $list = PriceList::create([
            'organization_id' => $this->organization->id,
            'code' => 'G'.uniqid(), 'name' => 'Barème',
            'scope' => PriceList::GLOBAL, 'is_active' => true,
        ]);

        return PriceRule::create([
            'price_list_id' => $list->id,
            'service_id' => $this->delivery->id,
            'code' => 'POIDS', 'name' => 'Au poids',
            'formula' => $formula, 'priority' => 100, 'is_active' => true,
        ]);
    };

    /** Une facture d'une ligne, adossée à une prestation de 350 kg. */
    $this->invoice = function (string $unitPrice = '100', string $status = 'draft'): Invoice {
        $order = Order::factory()->forOrganization($this->organization)
            ->create(['customer_id' => $this->customer->id, 'currency_code' => 'CHF']);

        $service = OrderService::factory()->create([
            'order_id' => $order->id,
            'service_id' => $this->delivery->id,
            'address_id' => Address::factory()->create(['postal_code' => '1204'])->id,
            'status' => 'completed',
            'weight' => 350,
        ]);

        $invoice = Invoice::factory()->forCustomer($this->customer)->create(['status' => $status]);

        InvoiceLine::factory()->forInvoice($invoice)->atLine(1)->create([
            'order_service_id' => $service->id,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'total_excluding_tax' => $unitPrice,
            'total_including_tax' => $unitPrice,
        ]);

        return $invoice;
    };

    $this->preview = fn (Invoice $invoice) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)->getJson("/api/v1/invoices/{$invoice->id}/repricing");

    $this->apply = fn (Invoice $invoice) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)->postJson("/api/v1/invoices/{$invoice->id}/reprice");
});

describe('aperçu', function (): void {
    /** L'écart se montre avant de s'appliquer. */
    it('annonce le nouveau prix sans rien écrire', function (): void {
        ($this->rule)('({P:poids}/{V:100})*{V:25}');
        $invoice = ($this->invoice)('100');

        ($this->preview)($invoice)
            ->assertOk()
            ->assertJsonPath('data.changes.0.currentUnitPrice', '100.00')
            ->assertJsonPath('data.changes.0.newUnitPrice', '87.50');

        expect($invoice->lines()->first()->unit_price)->toBe('100.00');
    });

    /** Une ligne déjà au bon prix n'a pas à figurer dans un écart. */
    it('ne signale rien quand le prix est déjà le bon', function (): void {
        ($this->rule)('{V:100}');

        ($this->preview)(($this->invoice)('100'))->assertOk()->assertJsonCount(0, 'data.changes');
    });

    /** §169BO : un tarif disparu ne devient pas un nouveau montant. */
    it('signale une ligne dont le tarif a disparu, sans la changer', function (): void {
        $invoice = ($this->invoice)('100');

        ($this->preview)($invoice)
            ->assertOk()
            ->assertJsonPath('data.changes.0.newUnitPrice', null)
            ->assertJsonPath('data.changes.0.reason', 'Tarif non configuré');
    });

    /** Aucun barème ne gouverne une ligne libre : on n'y touche pas. */
    it('ignore une ligne sans prestation', function (): void {
        $invoice = Invoice::factory()->forCustomer($this->customer)->create(['status' => 'draft']);
        InvoiceLine::factory()->forInvoice($invoice)->atLine(1)->create(['order_service_id' => null]);

        ($this->preview)($invoice)->assertOk()->assertJsonCount(0, 'data.changes');
    });
});

describe('application', function (): void {
    it('écrit les nouveaux prix et refait les totaux', function (): void {
        ($this->rule)('({P:poids}/{V:100})*{V:25}');
        $invoice = ($this->invoice)('100');

        ($this->apply)($invoice)->assertOk();

        expect($invoice->lines()->first()->unit_price)->toBe('87.50')
            ->and($invoice->fresh()->subtotal)->toBe('87.50');
    });

    it('journalise le recalcul', function (): void {
        ($this->rule)('{V:42}');

        ($this->apply)(($this->invoice)('100'))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.repriced']);
    });

    /** §169AN : une facture clôturée n'est jamais recalculée. */
    it('refuse de recalculer une facture clôturée', function (): void {
        ($this->rule)('{V:42}');

        ($this->apply)(($this->invoice)('100', 'closed'))->assertStatus(422);
    });

    it('refuse aussi d’en montrer l’écart', function (): void {
        ($this->preview)(($this->invoice)('100', 'closed'))->assertStatus(422);
    });
});
