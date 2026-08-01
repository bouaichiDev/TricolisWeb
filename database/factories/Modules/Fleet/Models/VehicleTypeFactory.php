<?php

namespace Database\Factories\Modules\Fleet\Models;

use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleType>
 */
class VehicleTypeFactory extends Factory
{
    public function modelName(): string
    {
        return VehicleType::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => fake()->unique()->bothify('VT-####'),
            'name' => fake()->randomElement(['Fourgon', 'Porteur 12T', 'Semi-remorque', 'Utilitaire']),
            'status' => 'active',
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }
}
