<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Shared\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'firstName' => ['sometimes', 'string', 'max:255'],
            'lastName' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user')?->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'preferredLanguage' => ['sometimes', 'string', 'max:10'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
        ];
    }
}
