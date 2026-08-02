<?php

namespace Database\Factories\Modules\Customers\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerSite>
 */
class CustomerSiteFactory extends Factory
{
    public function modelName(): string
    {
        return CustomerSite::class;
    }

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'address_id' => Address::factory(),
            'code' => fake()->unique()->word(),
            'name' => fake()->company(),
            'site_type' => fake()->randomElement(['warehouse', 'store', 'office']),
            'is_default' => false,
            'status' => 'active',
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => ['customer_id' => $customer->id]);
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
