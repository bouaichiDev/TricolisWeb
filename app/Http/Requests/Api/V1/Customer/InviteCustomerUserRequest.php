<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class InviteCustomerUserRequest extends FormRequest
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
            ...ServerControlledFields::prohibit(ServerControlledFields::MEMBER),
            'email' => ['required', 'string', 'email', 'max:255'],
            'firstName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'roleId' => ['nullable', 'string', 'ulid'],
        ];
    }
}
