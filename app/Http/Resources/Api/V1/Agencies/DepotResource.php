<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agencies;

use App\Modules\Agencies\Models\Depot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Depot */
class DepotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agencyId' => $this->agency_id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
