<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider;

use Illuminate\Foundation\Http\FormRequest;

class ProviderLoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            // Seulement pour un compte rattaché à plusieurs fournisseurs.
            'providerId' => ['nullable', 'string', 'ulid'],
        ];
    }
}
