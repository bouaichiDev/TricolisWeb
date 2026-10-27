<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Providers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Invitation d'un compte au portail d'un fournisseur (back-office).
 *
 * Le fournisseur vient de l'URL, l'organisation du contexte : les fournir est
 * refusé (422) plutôt qu'ignoré en silence.
 */
class InviteProviderUserRequest extends FormRequest
{
    public const array SERVER_CONTROLLED = [
        'providerId', 'provider_id', 'organizationId', 'organization_id',
        'userId', 'status', 'password', 'permissions', 'scope',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...array_fill_keys(self::SERVER_CONTROLLED, ['prohibited']),
            'email' => ['required', 'string', 'email', 'max:255'],
            'firstName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'roleId' => ['nullable', 'string', 'ulid'],
        ];
    }
}
