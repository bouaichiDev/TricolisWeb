<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Shared\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreOrganizationUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['firstName' => ['required', 'string', 'max:255'], 'lastName' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')], 'phone' => ['nullable', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::defaults()], 'preferredLanguage' => ['required', 'string', 'max:10'], 'isOwner' => ['required', 'boolean'], 'isPrimary' => ['required', 'boolean'], 'status' => ['required', Rule::enum(UserStatus::class)], 'roleIds' => ['sometimes', 'array'], 'roleIds.*' => ['required', 'ulid', 'distinct']];
    }
}
