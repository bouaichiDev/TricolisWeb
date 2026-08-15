<?php

namespace Database\Factories\Modules\Billing\Models;

use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Billing\Models\InvoiceLineAddressSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLineAddressSnapshot>
 */
class InvoiceLineAddressSnapshotFactory extends Factory
{
    public function modelName(): string
    {
        return InvoiceLineAddressSnapshot::class;
    }

    public function definition(): array
    {
        return [
            'invoice_line_id' => InvoiceLine::factory(),
            'address_code' => fake()->bothify('ADR-###'),
            'name' => fake()->company(),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'country' => 'MA',
        ];
    }

    public function forLine(InvoiceLine $line): static
    {
        return $this->state(fn (): array => ['invoice_line_id' => $line->id]);
    }
}
