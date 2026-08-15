<?php

namespace Database\Factories\Modules\Tours\Models;

use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourPeriod>
 */
class TourPeriodFactory extends Factory
{
    public function modelName(): string
    {
        return TourPeriod::class;
    }

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            // Facultatif au diagramme : une periode de conduite entre deux
            // arrets n'appartient a aucun arret.
            'tour_stop_id' => null,
            'period_type' => 'service',
            'sequence' => fake()->unique()->numberBetween(1, 9999),
            'break_minutes' => 0,
            'service_minutes' => 0,
            'waiting_minutes' => 0,
            'distance_meters' => 0,
            'internal_remark' => null,
            'status' => 'planned',
        ];
    }

    public function forTour(Tour $tour): static
    {
        return $this->state(fn (): array => ['tour_id' => $tour->id]);
    }

    public function forStop(TourStop $stop): static
    {
        return $this->state(fn (): array => [
            'tour_id' => $stop->tour_id,
            'tour_stop_id' => $stop->id,
        ]);
    }

    /**
     * Voir `TourStopFactory::atSequence()` : `sequence` est reserve par Laravel.
     */
    public function atSequence(int $sequence): static
    {
        return $this->state(fn (): array => ['sequence' => $sequence]);
    }
}
