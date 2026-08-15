<?php

namespace Database\Factories\Modules\Tours\Models;

use App\Modules\Packages\Models\Package;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Modules\Tours\Models\TourStopService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourPeriodAssignment>
 */
class TourPeriodAssignmentFactory extends Factory
{
    public function modelName(): string
    {
        return TourPeriodAssignment::class;
    }

    public function definition(): array
    {
        return [
            'tour_period_id' => TourPeriod::factory(),
            'tour_stop_service_id' => TourStopService::factory(),
            'package_id' => null,
        ];
    }

    /**
     * Période et service de la même tournée : l'API refuse de les croiser.
     */
    public function linking(TourPeriod $period, TourStopService $service): static
    {
        return $this->state(fn (): array => [
            'tour_period_id' => $period->id,
            'tour_stop_service_id' => $service->id,
        ]);
    }

    public function withPackage(Package $package): static
    {
        return $this->state(fn (): array => ['package_id' => $package->id]);
    }
}
