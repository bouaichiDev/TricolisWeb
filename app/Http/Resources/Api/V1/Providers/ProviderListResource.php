<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Providers;

use App\Modules\Providers\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fournisseur vu depuis une liste : ni chauffeurs, ni véhicules chargés.
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
            'addressId' => $this->address_id,
            'contactId' => $this->contact_id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'driverCount' => $this->whenCounted('drivers'),
            'vehicleCount' => $this->whenCounted('vehicles'),
        ];
    }
}
