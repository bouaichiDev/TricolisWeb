<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Drivers;

use App\Http\Resources\Api\V1\Providers\ProviderCompactResource;
use App\Modules\Drivers\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'un chauffeur.
 *
 * Le compte lié n'est exposé que par ses champs d'identification : ni statut,
 * ni rôles, ni aucune donnée sensible du `User`.
 *
 * @mixin Driver
 */
class DriverDetailResource extends JsonResource
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
            'provider' => new ProviderCompactResource($this->whenLoaded('provider')),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->id,
                'fullName' => $this->user->fullName(),
                'email' => $this->user->email,
            ]),
        ];
    }
}
