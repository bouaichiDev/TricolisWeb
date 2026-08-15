<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Http\Resources\Api\V1\Identity\UserCompactResource;
use App\Modules\Stock\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stockItemId' => $this->stock_item_id,
            'sourceLocationId' => $this->source_location_id,
            'destinationLocationId' => $this->destination_location_id,
            'movementType' => $this->movement_type,
            'quantity' => $this->quantity,
            'sourceEntityType' => $this->source_entity_type,
            'sourceEntityId' => $this->source_entity_id,
            'createdBy' => $this->created_by,
            'createdAt' => $this->created_at?->toIso8601String(),
            'stockItem' => new StockItemCompactResource($this->whenLoaded('stockItem')),
            'sourceLocation' => new StockLocationCompactResource($this->whenLoaded('sourceLocation')),
            'destinationLocation' => new StockLocationCompactResource($this->whenLoaded('destinationLocation')),
            'creator' => new UserCompactResource($this->whenLoaded('creator')),
        ];
    }
}
