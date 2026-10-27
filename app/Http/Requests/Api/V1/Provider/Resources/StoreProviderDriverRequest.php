<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Resources;

use App\Http\Requests\Api\V1\Provider\ProviderServerControlledFields;
use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Nouveau chauffeur du fournisseur courant.
 *
 * Fournisseur, organisation, statut, compte, adresse et contact ne se
 * choisissent pas ici : le carnet d'adresses de l'organisation n'est jamais
 * exposé au fournisseur. Un code ou un e-mail déjà pris est refusé sans dire
 * par qui.
 *
 * **L'e-mail est jugé en dernier**, une fois le reste valable : sinon une
 * saisie volontairement fausse ailleurs permettrait de sonder, sans rien
 * créer, quelles adresses ont un compte sur la plateforme.
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
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && User::where('email', Str::lower((string) $this->input('email')))->exists()) {
                $validator->errors()->add('email', 'Cette adresse e-mail ne peut pas être utilisée.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.unique' => 'Ce code ne peut pas être utilisé.'];
    }
}
