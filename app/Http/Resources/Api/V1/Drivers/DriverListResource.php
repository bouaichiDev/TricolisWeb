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
            'providerId' => $this->provider_id,
            'userId' => $this->user_id,
            'code' => $this->code,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => $this->fullName(),
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'providerName' => $this->whenLoaded('provider', fn () => $this->provider->name),
        ];
    }
}
