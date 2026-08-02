<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Addresses;

use App\Modules\Addresses\Models\EntityAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EntityAddress */
class EntityAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'addressId' => $this->address_id,
            'entityType' => $this->entity_type,
            'entityId' => $this->entity_id,
            'addressType' => $this->address_type,
            'isDefault' => $this->is_default,
            'address' => new AddressResource($this->whenLoaded('address')),
        ];
    }
}
