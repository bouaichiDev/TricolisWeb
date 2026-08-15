<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Drivers;

use App\Http\Resources\Api\V1\Addresses\AddressResource;
use App\Http\Resources\Api\V1\Contacts\ContactResource;
use App\Http\Resources\Api\V1\Providers\ProviderCompactResource;
use App\Modules\Drivers\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'un chauffeur.
 *
 * Les coordonnées du chauffeur relèvent de son `Contact` : le diagramme ne pose
 * ni téléphone ni courriel sur la classe.
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
            'organizationId' => $this->organization_id,
            'providerId' => $this->provider_id,
            'addressId' => $this->address_id,
            'contactId' => $this->contact_id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'provider' => new ProviderCompactResource($this->whenLoaded('provider')),
            'address' => new AddressResource($this->whenLoaded('address')),
            'contact' => new ContactResource($this->whenLoaded('contact')),
        ];
    }
}
