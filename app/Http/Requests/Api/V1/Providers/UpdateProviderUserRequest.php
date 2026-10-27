<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Providers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Changement du rôle d'un membre du portail fournisseur (back-office).
 */
class UpdateProviderUserRequest extends FormRequest
{
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
            ...array_fill_keys(InviteProviderUserRequest::SERVER_CONTROLLED, ['prohibited']),
            'roleId' => ['required', 'string', 'ulid'],
        ];
    }
}
