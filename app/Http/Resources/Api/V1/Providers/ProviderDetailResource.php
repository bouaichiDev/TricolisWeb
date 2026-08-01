<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Providers;

use App\Http\Resources\Api\V1\Organizations\OrganizationResource;
use App\Modules\Providers\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'un fournisseur.
 *
 * L'organisation et les compteurs ne sont restitués que s'ils ont été chargés
 * explicitement par le contrôleur.
 *
 * @mixin Provider
 */
class ProviderDetailResource extends JsonResource
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
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'driverCount' => $this->whenCounted('drivers'),
            'vehicleCount' => $this->whenCounted('vehicles'),
        ];
    }
}
