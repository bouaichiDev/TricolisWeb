<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\TourStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un arrêt : l'adresse opérationnelle (où aller, quand, consignes) et les
 * services à y réaliser. Seuls les services encore affectés à l'arrêt sont
 * rendus.
 *
 * @mixin TourStop
 */
class ProviderTourStopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = TourStopStatus::tryFrom((string) ($this->status instanceof TourStopStatus ? $this->status->value : $this->status));
        $address = $this->address;
        $moving = app(CurrentProviderContext::class)->can('provider.tour-stops.update-status')
            && $this->relationLoaded('tour') && $this->tour?->status === TourStatus::IN_PROGRESS;

        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'status' => $status?->value ?? (string) $this->status,
            'statusLabel' => $status?->label(),
            'plannedArrivalAt' => $this->planned_arrival_at?->toIso8601String(),
            'plannedDepartureAt' => $this->planned_departure_at?->toIso8601String(),
            'actualArrivalAt' => $this->actual_arrival_at?->toIso8601String(),
            'actualDepartureAt' => $this->actual_departure_at?->toIso8601String(),
            'address' => $address === null ? null : [
                'name' => $address->name,
                'line1' => $address->address_line_1,
                'line2' => $address->address_line_2,
                'postalCode' => $address->postal_code,
                'city' => $address->city,
                'country' => $address->country,
                'latitude' => $address->latitude === null ? null : (float) $address->latitude,
                'longitude' => $address->longitude === null ? null : (float) $address->longitude,
                'instructions' => $address->instructions,
                'timeWindowFrom' => $address->time_window_from,
                'timeWindowTo' => $address->time_window_to,
            ],
            'actions' => [
                'canArrive' => $moving && $status === TourStopStatus::PENDING,
                'canComplete' => $moving && $status === TourStopStatus::ARRIVED,
                'canSkip' => $moving && in_array($status, [TourStopStatus::PENDING, TourStopStatus::ARRIVED], true),
            ],
            // Seuls les services acquis : une composition en cours chez le
            // planificateur (`confirmed_at` nul) ne se voit pas ailleurs.
            'services' => ProviderStopServiceResource::collection(
                $this->services
                    ->filter(static fn ($service): bool => $service->is_active_assignment && $service->confirmed_at !== null)
                    ->sortBy('sequence_within_stop')->values(),
            ),
        ];
    }
}
