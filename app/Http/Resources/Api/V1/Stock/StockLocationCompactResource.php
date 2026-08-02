<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Modules\Stock\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockLocation
 */
class StockLocationCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'locationCode' => $this->location_code,
            'zoneCode' => $this->zone_code,
            'status' => $this->status,
        ];
    }
}
