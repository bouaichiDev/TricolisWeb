<?php

namespace Database\Factories\Modules\Organizations\Models;

use App\Modules\Organizations\Models\Organization;
use App\Shared\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function modelName(): string
    {
        return Organization::class;
    }

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->word(),
            'name' => fake()->company(),
            'legal_name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'preferred_language' => 'fr',
            'timezone' => 'Europe/Paris',
            'currency_code' => 'EUR',
            'status' => OrganizationStatus::ACTIVE,
            'settings' => [],
        ];
    }
}
