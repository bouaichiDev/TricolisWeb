<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agencies;

use App\Modules\Agencies\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Agency */
class AgencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'code' => $this->code,
            'name' => $this->name,
            'shortName' => $this->short_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'color' => $this->color,
            'loadingPoint' => $this->loading_point,
            'status' => $this->status,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
