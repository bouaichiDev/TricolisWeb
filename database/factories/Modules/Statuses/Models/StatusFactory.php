<?php

namespace Database\Factories\Modules\Statuses\Models;

use App\Modules\Statuses\Models\Status;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Status>
 */
class StatusFactory extends Factory
{
    public function modelName(): string
    {
        return Status::class;
    }

    public function definition(): array
    {
        return [
            'source' => MorphMap::ORDER,
            'status' => fake()->unique()->numberBetween(100, 9999),
            'code' => fake()->unique()->lexify('statut_????'),
            'label' => fake()->words(2, true),
            'icon' => null,
            'active' => true,
            'is_to_send' => false,
            'position' => null,
        ];
    }

    public function forSource(string $source): static
    {
        return $this->state(fn (): array => ['source' => $source]);
    }
}
