<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Providers;

use App\Modules\Providers\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fournisseur vu depuis une liste : ni chauffeurs, ni véhicules chargés.
 *
 * `legacyId` n'est pas exposé — il ne sert qu'à la reprise de données.
 *
 * @mixin Provider
 */
class ProviderListResource extends JsonResource
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
            'providerType' => $this->provider_type,
            'status' => $this->status,
            'driverCount' => $this->whenCounted('drivers'),
            'vehicleCount' => $this->whenCounted('vehicles'),
        ];
    }
}
