<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

use App\Modules\Tours\Models\TourPeriodAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Affectation d'un service — et éventuellement d'un colis — à une période.
 *
 * Trois clés étrangères, pas une de plus : la classe n'en porte pas d'autres.
 *
 * @mixin TourPeriodAssignment
 */
class TourPeriodAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tourPeriodId' => $this->tour_period_id,
            'tourStopServiceId' => $this->tour_stop_service_id,
            'packageId' => $this->package_id,
            'tourStopService' => new TourStopServiceResource($this->whenLoaded('tourStopService')),
        ];
    }
}
