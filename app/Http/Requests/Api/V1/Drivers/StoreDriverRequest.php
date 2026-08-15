<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Drivers;

use App\Modules\Providers\Models\Provider;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
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
        return [
            'providerId' => [
                'required', 'ulid',
                new BelongsToActiveOrganization(Provider::class, null, 'Ce fournisseur n’appartient pas à l’organisation active.'),
            ],
            'addressId' => ['nullable', 'string', Rule::exists('addresses', 'id')],
            'contactId' => ['nullable', 'string', Rule::exists('contacts', 'id')],
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('drivers', 'code')->where('provider_id', $this->input('providerId')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:32'],
        ];
    }
}
