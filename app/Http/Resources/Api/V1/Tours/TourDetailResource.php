<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

use App\Http\Resources\Api\V1\Drivers\DriverCompactResource;
use App\Http\Resources\Api\V1\Fleet\VehicleCompactResource;
use App\Http\Resources\Api\V1\Providers\ProviderCompactResource;
use App\Modules\Tours\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'une tournée.
 *
 * Les relations ne sont restituées que si le contrôleur les a explicitement
 * chargées : le §26 interdit de tirer tout l'agrégat sur une lecture.
 *
 * @mixin Tour
 */
class TourDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'tourNumber' => $this->tour_number,
            'tourDate' => $this->tour_date?->toDateString(),
            'agencyId' => $this->agency_id,
            'depotId' => $this->depot_id,
            'providerId' => $this->provider_id,
            'vehicleId' => $this->vehicle_id,
            'driverId' => $this->driver_id,
            'tourType' => $this->tour_type,
            'instructions' => $this->instructions,
            'plannedStartAt' => $this->planned_start_at?->toIso8601String(),
            'plannedEndAt' => $this->planned_end_at?->toIso8601String(),
            'actualStartAt' => $this->actual_start_at?->toIso8601String(),
            'actualEndAt' => $this->actual_end_at?->toIso8601String(),
            'totalWeight' => $this->total_weight,
            'totalVolume' => $this->total_volume,
            'totalPackages' => $this->total_packages,
            'totalCustomers' => $this->total_customers,
            'drivingTimeMinutes' => $this->driving_time_minutes,
            'workingTimeMinutes' => $this->working_time_minutes,
            'distanceMeters' => $this->distance_meters,
            'status' => $this->status->value,
            'provider' => new ProviderCompactResource($this->whenLoaded('provider')),
            'driver' => new DriverCompactResource($this->whenLoaded('driver')),
            'vehicle' => new VehicleCompactResource($this->whenLoaded('vehicle')),
            'stops' => TourStopResource::collection($this->whenLoaded('stops')),
            'periods' => TourPeriodResource::collection($this->whenLoaded('periods')),
            'stopCount' => $this->whenCounted('stops'),
            'periodCount' => $this->whenCounted('periods'),
        ];
    }
}
