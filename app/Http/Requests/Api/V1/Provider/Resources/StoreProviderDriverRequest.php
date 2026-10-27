<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Resources;

use App\Http\Requests\Api\V1\Provider\ProviderServerControlledFields;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nouveau chauffeur du fournisseur courant.
 *
 * Fournisseur, organisation, statut, compte, adresse et contact ne se
 * choisissent pas ici : le carnet d'adresses de l'organisation n'est jamais
 * exposé au fournisseur. Un code ou un e-mail déjà pris est refusé sans dire
 * par qui.
 */
class StoreProviderDriverRequest extends FormRequest
{
    public const array PROHIBITED = [...ProviderServerControlledFields::TENANT, 'userId', 'addressId', 'contactId'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentProviderContext::class)->organizationId();

        return [
            ...ProviderServerControlledFields::prohibit(self::PROHIBITED),
            'code' => ['required', 'string', 'max:64', Rule::unique('drivers', 'code')->where('organization_id', $organizationId)],
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code ne peut pas être utilisé.',
            'email.unique' => 'Cette adresse e-mail ne peut pas être utilisée.',
        ];
    }
}
