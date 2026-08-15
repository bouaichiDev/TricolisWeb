<?php

namespace Database\Factories\Modules\Stock\Models;

use App\Modules\Agencies\Models\Depot;
use App\Modules\Stock\Models\StockLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLocation>
 */
class StockLocationFactory extends Factory
{
    public function modelName(): string
    {
        return StockLocation::class;
    }

    public function definition(): array
    {
        return [
            'depot_id' => Depot::factory(),
            'parent_location_id' => null,
            'zone_code' => fake()->randomElement(['A', 'B', 'C']),
            'aisle' => (string) fake()->numberBetween(1, 20),
            'rack' => (string) fake()->numberBetween(1, 10),
            'level' => (string) fake()->numberBetween(1, 5),
            'location_code' => fake()->unique()->bothify('LOC-#####'),
            'barcode' => null,
            'status' => 'active',
        ];
    }

    public function forDepot(Depot $depot): static
    {
        return $this->state(fn (): array => ['depot_id' => $depot->id]);
    }

    /**
     * Enfant d'un emplacement, dans le meme depot : l'API refuse un parent
     * d'un autre depot.
     */
    public function childOf(StockLocation $parent): static
    {
        return $this->state(fn (): array => [
            'depot_id' => $parent->depot_id,
            'parent_location_id' => $parent->id,
        ]);
    }
}
