<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Fleet;

use App\Http\Resources\Api\V1\Providers\ProviderCompactResource;
use App\Http\Resources\Api\V1\Types\TypeItemResource;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vehicle */
class VehicleDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'providerId' => $this->provider_id,
            'vehicleTypeId' => $this->vehicle_type_id,
            'code' => $this->code,
            'registrationNumber' => $this->registration_number,
            'payloadCapacity' => $this->payload_capacity,
            'volumeCapacity' => $this->volume_capacity,
            'palletCapacity' => $this->pallet_capacity,
            'status' => $this->status,
            'provider' => new ProviderCompactResource($this->whenLoaded('provider')),
            'vehicleType' => new TypeItemResource($this->whenLoaded('vehicleType')),
        ];
    }
}
