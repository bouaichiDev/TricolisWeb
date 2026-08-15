<?php

namespace Database\Factories\Modules\Integrations\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerApiConfiguration>
 */
class CustomerApiConfigurationFactory extends Factory
{
    public function modelName(): string
    {
        return CustomerApiConfiguration::class;
    }

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->unique()->bothify('API-####'),
            // Empreinte d'une cle jetable : aucun test n'a besoin de la valeur
            // en clair, et la stocker serait exactement ce que le code interdit.
            'api_key_hash' => hash('sha256', Str::random(64)),
            'allowed_ips' => null,
            'permissions' => null,
            'is_active' => true,
            'last_used_at' => null,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => ['customer_id' => $customer->id]);
    }
}
