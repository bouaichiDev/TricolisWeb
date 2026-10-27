<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Le profil vu du portail fournisseur : la fiche du fournisseur, en lecture
 * seule, et le compte connecté.
 *
 * @property-read CurrentProviderContext $resource
 */
class ProviderProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $provider = $this->resource->provider()->loadMissing(['address', 'contact']);
        $user = $this->resource->user()->fresh();
        $address = $provider->address;
        $contact = $provider->contact;

        return [
            'provider' => [
                'code' => $provider->code,
                'name' => $provider->name,
                'status' => $provider->status,
                'address' => $address === null ? null : [
                    'line1' => $address->address_line_1,
                    'line2' => $address->address_line_2,
                    'postalCode' => $address->postal_code,
                    'city' => $address->city,
                    'country' => $address->country,
                ],
                'contact' => $contact === null ? null : [
                    'firstName' => $contact->first_name,
                    'lastName' => $contact->last_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                ],
            ],
            'user' => [
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'preferredLanguage' => $user->preferred_language,
            ],
        ];
    }
}
