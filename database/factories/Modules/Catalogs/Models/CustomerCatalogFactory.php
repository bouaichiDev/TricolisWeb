<?php

namespace Database\Factories\Modules\Catalogs\Models;

use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCatalog>
 */
class CustomerCatalogFactory extends Factory
{
    public function modelName(): string
    {
        return CustomerCatalog::class;
    }

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'code' => fake()->unique()->bothify('CAT-####'),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => ['customer_id' => $customer->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
