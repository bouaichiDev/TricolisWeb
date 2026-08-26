<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

use App\Modules\Tours\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tournée vue depuis une liste : ni période ni affectation chargée, seulement
 * leurs compteurs.
 *
 * Les arrêts font exception, et seulement sur demande — `?withStops=1`. La vue
 * en colonnes les montre sous chaque tournée ; les charger toujours coûterait
 * une jointure à qui ne veut qu'une liste.
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
            'stops' => TourStopResource::collection($this->whenLoaded('stops')),
            'periodCount' => $this->whenCounted('periods'),
        ];
    }
}
