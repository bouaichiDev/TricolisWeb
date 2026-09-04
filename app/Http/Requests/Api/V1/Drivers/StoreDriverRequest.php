<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Drivers;

use App\Modules\Addresses\Models\Address;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Providers\Models\Provider;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use App\Shared\Http\Rules\ExistsInStatusReferential;
use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un chauffeur.
 *
 * Le fournisseur n'est pas seulement vérifié comme existant : il doit
 * appartenir à l'organisation active. `organizationId` n'est pas accepté — il
 * est déduit du fournisseur retenu.
 *
 * `addressId` et `contactId` sont facultatifs : le diagramme les pose en `0..1`.
 */
class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganizationContext::class)->getOrganizationId();

        return [
            // Facultatif : un transporteur emploie ses propres chauffeurs.
            'providerId' => [
                'nullable', 'ulid',
                new BelongsToActiveOrganization(Provider::class, null, 'Ce fournisseur n’appartient pas à l’organisation active.'),
            ],
            'addressId' => [
                'nullable', 'ulid',
                new BelongsToActiveOrganization(Address::class, 'entityAddresses', 'Cette adresse n’appartient pas à l’organisation active.'),
            ],
            'contactId' => [
                'nullable', 'ulid',
                new BelongsToActiveOrganization(Contact::class, 'entityContacts', 'Ce contact n’appartient pas à l’organisation active.'),
            ],
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('drivers', 'code')->where('organization_id', $organizationId),
            ],
            // L'identite sert au compte autant qu'au chauffeur : `name` est
            // compose des deux, et l'adresse ouvre l'application mobile.
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'string', 'max:32', new ExistsInStatusReferential('driver')],
        ];
    }
}
