<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Providers;

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
 * `addressId` et `contactId` sont facultatifs et validés par simple existence :
 * `addresses` et `contacts` sont des tables partagées, sans `organization_id`,
 * comme pour `customer_sites` et `order_services`.
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
            'addressId' => ['nullable', 'string', Rule::exists('addresses', 'id')],
            'contactId' => ['nullable', 'string', Rule::exists('contacts', 'id')],
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('providers', 'code')->where('organization_id', $organizationId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:32', new ExistsInStatusReferential('provider')],
        ];
    }
}
