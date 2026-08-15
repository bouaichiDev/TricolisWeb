<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Fleet;

use App\Modules\Fleet\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VehicleType */
class VehicleTypeListResource extends JsonResource
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
            'status' => $this->status,
            'vehicleCount' => $this->whenCounted('vehicles'),
        ];
    }
}
