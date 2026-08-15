<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tracking;

use App\Http\Resources\Api\V1\Identity\UserCompactResource;
use App\Modules\Tracking\Models\TrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'un événement de suivi.
 *
 * @mixin TrackingEvent
 */
class TrackingEventDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'orderId' => $this->order_id,
            'orderServiceId' => $this->order_service_id,
            'tourId' => $this->tour_id,
            'tourStopId' => $this->tour_stop_id,
            'eventType' => $this->event_type,
            'status' => $this->status,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'occurredAt' => $this->occurred_at?->toIso8601String(),
            'createdBy' => $this->created_by,
            'creator' => new UserCompactResource($this->whenLoaded('creator')),
        ];
    }
}
