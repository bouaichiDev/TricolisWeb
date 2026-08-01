<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Drivers;

use App\Modules\Identity\Models\User;
use App\Modules\Providers\Models\Provider;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un chauffeur.
 *
 * Le fournisseur et le compte utilisateur ne sont pas seulement vérifiés comme
 * existants : ils doivent appartenir à l'organisation active.
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
            'legacyId' => ['nullable', 'integer', 'min:0'],
            'providerId' => [
                'required', 'ulid',
                new BelongsToActiveOrganization(Provider::class, null, 'Ce fournisseur n’appartient pas à l’organisation active.'),
            ],
            'userId' => [
                'nullable', 'ulid',
                new BelongsToActiveOrganization(User::class, 'organizationUsers', 'Cet utilisateur n’est pas accessible dans l’organisation active.'),
            ],
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('drivers', 'code')->where('provider_id', $this->input('providerId')),
            ],
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'string', 'max:32'],
        ];
    }
}
