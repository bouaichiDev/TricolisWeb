<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tours;

use App\Modules\Tours\Models\TourStopService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Service planifié sur un arrêt.
 *
 * @mixin TourStopService
 */
class TourStopServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tourStopId' => $this->tour_stop_id,
            'orderServiceId' => $this->order_service_id,
            'sequenceWithinStop' => $this->sequence_within_stop,
            'isActiveAssignment' => $this->is_active_assignment,
            'status' => $this->status,
            'orderServiceNumber' => $this->whenLoaded('orderService', fn () => $this->orderService->service_number),
            'assignmentCount' => $this->whenCounted('assignments'),
        ];
    }
}
