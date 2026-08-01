<?php

namespace Database\Factories\Modules\Packages\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Packages\Models\PackageType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageType>
 */
class PackageTypeFactory extends Factory
{
    public function modelName(): string
    {
        return PackageType::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => fake()->unique()->bothify('PKG-??##'),
            'name' => fake()->randomElement(['Palette', 'Carton', 'Rolls', 'Bac']),
            'status' => 'active',
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }
}
