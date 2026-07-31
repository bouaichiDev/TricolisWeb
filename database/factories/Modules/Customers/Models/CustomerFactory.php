<?php

namespace Database\Factories\Modules\Customers\Models;

use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function modelName(): string
    {
        return Customer::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => fake()->unique()->word(),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'status' => CustomerStatus::ACTIVE,
        ];
    }
}
