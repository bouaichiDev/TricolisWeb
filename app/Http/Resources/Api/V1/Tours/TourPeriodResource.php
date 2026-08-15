<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

use App\Modules\Tours\Models\TourPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Période vue depuis une liste.
 *
 * @mixin TourPeriod
 */
class TourPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tourId' => $this->tour_id,
            'tourStopId' => $this->tour_stop_id,
            'periodType' => $this->period_type,
            'sequence' => $this->sequence,
            'plannedStartAt' => $this->planned_start_at?->toIso8601String(),
            'plannedEndAt' => $this->planned_end_at?->toIso8601String(),
            'actualStartAt' => $this->actual_start_at?->toIso8601String(),
            'actualEndAt' => $this->actual_end_at?->toIso8601String(),
            'breakMinutes' => $this->break_minutes,
            'serviceMinutes' => $this->service_minutes,
            'waitingMinutes' => $this->waiting_minutes,
            'distanceMeters' => $this->distance_meters,
            'status' => $this->status,
            'assignmentCount' => $this->whenCounted('assignments'),
        ];
    }
}
