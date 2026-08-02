<?php

namespace Database\Factories\Modules\Agencies\Models;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Depot>
 */
class DepotFactory extends Factory
{
    public function modelName(): string
    {
        return Depot::class;
    }

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'code' => fake()->unique()->word(),
            'name' => fake()->company().' Dépôt',
            'status' => 'active',
        ];
    }

    public function forAgency(Agency $agency): static
    {
        return $this->state(fn (): array => ['agency_id' => $agency->id]);
    }
}
