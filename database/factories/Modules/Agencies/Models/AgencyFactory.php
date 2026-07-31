<?php

namespace Database\Factories\Modules\Agencies\Models;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    public function modelName(): string
    {
        return Agency::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => fake()->unique()->word(),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'status' => 'active',
        ];
    }
}
