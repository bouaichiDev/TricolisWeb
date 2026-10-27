<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Orders;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\MorphMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une prestation de la commande : service, adresse, créneau, contacts,
 * colis. Ni prix client ni coût fournisseur, ni temps de planification.
 *
 * @mixin OrderService
 */
class CustomerOrderServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $address = $this->address;

        return [
            'id' => $this->id,
            'serviceNumber' => $this->service_number,
            'sequence' => $this->sequence,
            'service' => $this->service ? ['id' => $this->service->id, 'code' => $this->service->code, 'name' => $this->service->name] : null,
            'requestedDate' => $this->requested_date?->toDateString(),
            'requestedFrom' => $this->requested_from?->format('H:i'),
            'requestedTo' => $this->requested_to?->format('H:i'),
            'instructions' => $this->instructions,
            'status' => app(CustomerStatusLabels::class)->present(MorphMap::ORDER_SERVICE, $this->status),
            'address' => $address === null ? null : [
                'id' => $address->id,
                'name' => $address->name,
                'addressLine1' => $address->address_line_1,
                'addressLine2' => $address->address_line_2,
                'postalCode' => $address->postal_code,
                'city' => $address->city,
                'country' => $address->country,
                'instructions' => $address->instructions,
            ],
            'contacts' => $this->contacts->map(static fn ($contact): array => [
                'contactRole' => $contact->contact_role?->value,
                'isPrimary' => (bool) $contact->is_primary,
                'firstName' => $contact->first_name_snapshot,
                'lastName' => $contact->last_name_snapshot,
                'phone' => $contact->phone_snapshot,
                'mobile' => $contact->mobile_snapshot,
                'email' => $contact->email_snapshot,
            ])->values()->all(),
            'packages' => $this->servicePackages->map(static fn ($link): array => [
                'packageId' => $link->package_id,
                'quantity' => $link->quantity,
            ])->values()->all(),
        ];
    }
}
