<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Le mot de passe n'est exigé que pour un compte né de l'invitation : c'est
 * `ActivateCustomerInvitation` qui le sait, et qui le réclame.
 */
class ActivateCustomerAccountRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:255'],
            'firstName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'confirmed', PasswordRule::defaults()],
        ];
    }
}
