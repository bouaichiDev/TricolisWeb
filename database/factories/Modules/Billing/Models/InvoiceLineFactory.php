<?php

namespace Database\Factories\Modules\Billing\Models;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    public function modelName(): string
    {
        return InvoiceLine::class;
    }

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            // Nul par defaut : une ligne libre ne consomme pas l'unicite sur
            // order_service_id, ce qui rend les jeux de test composables.
            'order_service_id' => null,
            'order_id' => null,
            'line_number' => fake()->unique()->numberBetween(1, 9999),
            'service_code' => null,
            'description' => fake()->sentence(3),
            'customer_order_reference' => null,
            'quantity' => 1,
            'unit_price' => 100,
            'discount_rate' => 0,
            'tax_rate' => 0,
            'total_excluding_tax' => 100,
            'total_including_tax' => 100,
            'service_completed_at' => null,
            'status' => 'billable',
        ];
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (): array => ['invoice_id' => $invoice->id]);
    }

    public function atLine(int $lineNumber): static
    {
        return $this->state(fn (): array => ['line_number' => $lineNumber]);
    }
}
