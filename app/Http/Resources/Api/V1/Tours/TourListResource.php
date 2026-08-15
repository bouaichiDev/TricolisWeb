<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

use App\Modules\Tours\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tournée vue depuis une liste : aucun arrêt, aucune période, aucune
 * affectation chargée — seulement leurs compteurs.
 *
 * @mixin Tour
 */
class TourListResource extends JsonResource
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
            'plannedStartAt' => $this->planned_start_at?->toIso8601String(),
            'plannedEndAt' => $this->planned_end_at?->toIso8601String(),
            'totalWeight' => $this->total_weight,
            'totalVolume' => $this->total_volume,
            'totalPackages' => $this->total_packages,
            'totalCustomers' => $this->total_customers,
            'distanceMeters' => $this->distance_meters,
            'status' => $this->status->value,
            'agencyName' => $this->whenLoaded('agency', fn () => $this->agency->name),
            'stopCount' => $this->whenCounted('stops'),
            'periodCount' => $this->whenCounted('periods'),
        ];
    }
}
