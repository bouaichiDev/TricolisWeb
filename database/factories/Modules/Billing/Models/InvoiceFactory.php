<?php

namespace Database\Factories\Modules\Billing\Models;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Customers\Models\Customer;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function modelName(): string
    {
        return Invoice::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            // Le client doit relever de la meme organisation que la facture.
            'customer_id' => fn (array $attributes): Customer => Customer::factory()->create([
                'organization_id' => $attributes['organization_id'],
            ]),
            'invoice_number' => fake()->unique()->bothify('INV-2026-#####'),
            'invoice_date' => now()->toDateString(),
            'period_from' => null,
            'period_to' => null,
            'currency_code' => 'MAD',
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'external_reference' => null,
            'remark' => null,
            'status' => 'draft',
            'created_at' => now(),
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
        ]);
    }
}
