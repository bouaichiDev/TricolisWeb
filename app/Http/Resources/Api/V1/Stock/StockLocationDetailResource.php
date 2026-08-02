<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Modules\Stock\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockLocation
 */
class StockLocationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'depotId' => $this->depot_id,
            'parentLocationId' => $this->parent_location_id,
            'zoneCode' => $this->zone_code,
            'aisle' => $this->aisle,
            'rack' => $this->rack,
            'level' => $this->level,
            'locationCode' => $this->location_code,
            'barcode' => $this->barcode,
            'status' => $this->status,
            'parent' => new StockLocationCompactResource($this->whenLoaded('parent')),
            'children' => StockLocationCompactResource::collection($this->whenLoaded('children')),
            'balances' => StockBalanceListResource::collection($this->whenLoaded('balances')),
        ];
    }
}
