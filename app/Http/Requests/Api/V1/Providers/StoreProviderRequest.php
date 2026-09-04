<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Providers;

use App\Modules\Addresses\Models\Address;
use App\Modules\Contacts\Models\Contact;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use App\Shared\Http\Rules\ExistsInStatusReferential;
use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un fournisseur.
 *
 * `organizationId` n'est pas accepté en entrée : le fournisseur est créé dans
 * l'organisation active, pour qu'aucun appelant ne puisse en viser une autre.
 * `status` reste une chaîne libre — le diagramme n'en énumère pas les valeurs.
 *
 * `addressId` et `contactId` sont facultatifs, et cloisonnés par organisation.
 * Ces deux tables n'ont pas d'`organization_id` — elles le tiennent de leurs
 * liaisons, `entity_addresses` et `entity_contacts`. Une simple vérification
 * d'existence laissait donc rattacher l'adresse d'une autre organisation, et la
 * rendait ensuite lisible par la fiche.
 */
class StoreProviderRequest extends FormRequest
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
                Rule::unique('providers', 'code')->where('organization_id', $organizationId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:32', new ExistsInStatusReferential('provider')],
        ];
    }
}
