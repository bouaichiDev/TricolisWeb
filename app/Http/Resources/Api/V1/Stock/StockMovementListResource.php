<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Modules\Stock\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementListResource extends JsonResource
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
        ];
    }
}
