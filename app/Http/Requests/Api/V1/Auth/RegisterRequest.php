<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'preferredLanguage' => ['nullable', 'string', 'max:10'],
            'deviceName' => ['nullable', 'string', 'max:255'],

            'organization' => ['required', 'array'],
            'organization.name' => ['required', 'string', 'max:255'],
            'organization.code' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('organizations', 'code')],
            'organization.legalName' => ['nullable', 'string', 'max:255'],
            'organization.registrationNumber' => ['nullable', 'string', 'max:255'],
            'organization.taxNumber' => ['nullable', 'string', 'max:255'],
            'organization.email' => ['nullable', 'email', 'max:255'],
            'organization.phone' => ['nullable', 'string', 'max:255'],
            'organization.preferredLanguage' => ['nullable', 'string', 'max:10'],
            'organization.timezone' => ['nullable', 'timezone'],
            'organization.currencyCode' => ['nullable', 'string', 'size:3', 'uppercase'],
        ];
    }
}
