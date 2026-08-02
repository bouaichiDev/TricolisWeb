<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Addresses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Addresses\StoreAddressContactRequest;
use App\Http\Resources\Api\V1\Addresses\AddressContactResource;
use App\Modules\Addresses\Models\Address;
use App\Modules\Contacts\Models\AddressContact;
use App\Modules\Contacts\Models\Contact;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contacts rattachés directement à une adresse (AddressContact).
 */
class AddressContactController extends Controller
{
    /**
     * Lister les contacts d'une adresse.
     *
     * Permission requise : `addresses.view`.
     */
    public function index(Request $request, Address $address): JsonResponse
    {
        $this->requireOrganizationId();
        $this->authorize('view', $address);

        $links = AddressContact::where('address_id', $address->id)->with('contact')->get();

        return ApiResponse::ok(AddressContactResource::collection($links));
    }

    /**
     * Rattacher un contact de l'organisation active à une adresse.
     *
     * Permission requise : `addresses.update`.
     */
    public function store(StoreAddressContactRequest $request, Address $address): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('update', $address);

        $data = $request->validated();
        $contact = Contact::whereKey($data['contactId'])
            ->whereHas('entityContacts', fn ($query) => $query->where('organization_id', $organizationId))
            ->firstOrFail();

        $role = $data['contactRole'] ?? 'other';

        $exists = AddressContact::where('address_id', $address->id)
            ->where('contact_id', $contact->id)
            ->where('contact_role', $role)
            ->exists();

        if ($exists) {
            return ApiResponse::error('Ce contact est déjà rattaché à cette adresse.', 409);
        }

        $link = AddressContact::create([
            'address_id' => $address->id,
            'contact_id' => $contact->id,
            'contact_role' => $role,
            'is_primary' => $data['isPrimary'] ?? false,
        ]);
        $this->audit($request, $organizationId, 'address_contact_linked', $link, null, $link->toArray());

        return ApiResponse::created(new AddressContactResource($link->load('contact')));
    }

    /**
     * Détacher un contact d'une adresse.
     *
     * Permission requise : `addresses.update`.
     */
    public function destroy(Request $request, Address $address, AddressContact $addressContact): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('update', $address);

        if ($addressContact->address_id !== $address->id) {
            return ApiResponse::error('Contact introuvable pour cette adresse.', 404);
        }

        $this->audit($request, $organizationId, 'address_contact_unlinked', $addressContact, $addressContact->toArray(), null);
        $addressContact->delete();

        return ApiResponse::noContent();
    }
}
