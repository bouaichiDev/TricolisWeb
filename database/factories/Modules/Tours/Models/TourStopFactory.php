<?php

namespace Database\Factories\Modules\Tours\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourStop>
 */
class TourStopFactory extends Factory
{
    public function modelName(): string
    {
        return TourStop::class;
    }

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'address_id' => Address::factory(),
            // Sequence unique dans la tournee : l'index (tour_id, sequence) la
            // refuserait sinon des le deuxieme arret.
            'sequence' => fake()->unique()->numberBetween(1, 9999),
            'grouping_key' => null,
            'generation_mode' => null,
            'waiting_minutes' => 0,
            'service_minutes' => 0,
            'status' => TourStopStatus::PENDING,
        ];
    }

    public function forTour(Tour $tour): static
    {
        return $this->state(fn (): array => ['tour_id' => $tour->id]);
    }

    /**
     * Nommee `atSequence` et non `sequence` : ce dernier est deja pris par
     * `Factory::sequence()`, qui fait alterner des etats.
     */
    public function atSequence(int $sequence): static
    {
        return $this->state(fn (): array => ['sequence' => $sequence]);
    }
}
