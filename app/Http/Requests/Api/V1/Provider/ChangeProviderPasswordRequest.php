<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ChangeProviderPasswordRequest extends FormRequest
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
            'currentPassword' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ];
    }
}
