<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Addresses;

use App\Http\Resources\Api\V1\Contacts\ContactResource;
use App\Modules\Contacts\Models\AddressContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AddressContact */
class AddressContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'addressId' => $this->address_id,
            'contactId' => $this->contact_id,
            'contactRole' => $this->contact_role?->value,
            'isPrimary' => $this->is_primary,
            'contact' => new ContactResource($this->whenLoaded('contact')),
        ];
    }
}
