<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Modules\Stock\Models\StockReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockReservation
 */
class StockReservationListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stockItemId' => $this->stock_item_id,
            'stockLocationId' => $this->stock_location_id,
            'orderLineId' => $this->order_line_id,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'reservedAt' => $this->reserved_at?->toIso8601String(),
            'releasedAt' => $this->released_at?->toIso8601String(),
        ];
    }
}
