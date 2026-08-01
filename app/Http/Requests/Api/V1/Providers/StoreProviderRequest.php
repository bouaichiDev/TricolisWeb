<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Providers;

use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un fournisseur.
 *
 * `organizationId` n'est pas accepté en entrée : le fournisseur est créé dans
 * l'organisation active, pour qu'aucun appelant ne puisse en viser une autre.
 * `providerType` et `status` restent des chaînes libres — le diagramme n'en
 * énumère pas les valeurs.
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
            'legacyId' => ['nullable', 'integer', 'min:0'],
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('providers', 'code')->where('organization_id', $organizationId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'providerType' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'max:32'],
        ];
    }
}
