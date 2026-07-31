<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Addresses;

use App\Modules\Addresses\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Address */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'addressLine1' => $this->address_line_1,
            'addressLine2' => $this->address_line_2,
            'addressLine3' => $this->address_line_3,
            'floor' => $this->floor,
            'addressNumber' => $this->address_number,
            'route' => $this->route,
            'sublocality' => $this->sublocality,
            'postalCode' => $this->postal_code,
            'city' => $this->city,
            'town' => $this->town,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'instructions' => $this->instructions,
            'timeWindowFrom' => $this->time_window_from,
            'timeWindowTo' => $this->time_window_to,
            'isDefault' => $this->is_default,
            'status' => $this->status,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
