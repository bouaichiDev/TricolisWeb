<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Drivers;

use App\Modules\Drivers\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chauffeur vu depuis une liste.
 *
 * @mixin Driver
 */
class DriverListResource extends JsonResource
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
            'addressId' => $this->address_id,
            'contactId' => $this->contact_id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'providerName' => $this->whenLoaded('provider', fn () => $this->provider->name),
        ];
    }
}
