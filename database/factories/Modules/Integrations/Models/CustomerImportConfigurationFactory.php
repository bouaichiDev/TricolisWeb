<?php

namespace Database\Factories\Modules\Integrations\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerImportConfiguration>
 */
class CustomerImportConfigurationFactory extends Factory
{
    public function modelName(): string
    {
        return CustomerImportConfiguration::class;
    }

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->unique()->bothify('IMPORT-####'),
            'source_type' => 'sftp',
            'file_format' => 'csv',
            'mapping' => null,
            'validation_rules' => null,
            'is_active' => true,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => ['customer_id' => $customer->id]);
    }
}
