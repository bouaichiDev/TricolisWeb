<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Providers;

use App\Http\Resources\Api\V1\Addresses\AddressResource;
use App\Http\Resources\Api\V1\Contacts\ContactResource;
use App\Http\Resources\Api\V1\Organizations\OrganizationResource;
use App\Modules\Providers\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'un fournisseur.
 *
 * L'organisation, l'adresse, le contact et les compteurs ne sont restitués que
 * s'ils ont été chargés explicitement par le contrôleur.
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
            'addressId' => $this->address_id,
            'contactId' => $this->contact_id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'address' => new AddressResource($this->whenLoaded('address')),
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'driverCount' => $this->whenCounted('drivers'),
            'vehicleCount' => $this->whenCounted('vehicles'),
        ];
    }
}
