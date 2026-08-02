<?php

namespace Database\Factories\Modules\Packages\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Packages\Models\GroupingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupingType>
 */
class GroupingTypeFactory extends Factory
{
    public function modelName(): string
    {
        return GroupingType::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => fake()->unique()->bothify('GRP-??##'),
            'name' => fake()->randomElement(['Lot', 'Envoi', 'Groupage']),
            'status' => 'active',
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }
}
