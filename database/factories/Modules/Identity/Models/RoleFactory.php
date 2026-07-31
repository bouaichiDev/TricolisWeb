<?php

namespace Database\Factories\Modules\Identity\Models;

use App\Modules\Identity\Models\Role;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function modelName(): string
    {
        return Role::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => fake()->unique()->word(),
            'name' => fake()->jobTitle(),
            'scope' => 'organization',
            'is_system' => false,
            'status' => 'active',
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }
}
