<?php

namespace Database\Factories\Modules\Orders\Models;

use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function modelName(): string
    {
        return Service::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => fake()->unique()->bothify('SRV-??##'),
            'name' => fake()->randomElement(['Livraison', 'Enlèvement', 'Montage', 'Reprise']),
            'unit' => 'delivery',
            'default_duration_minutes' => fake()->numberBetween(15, 120),
            'billable_to_customer' => true,
            'payable_to_provider' => true,
            'requires_address' => true,
            'requires_contact' => false,
            'status' => 'active',
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }
}
