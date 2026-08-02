<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Shared\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['firstName' => ['sometimes', 'string', 'max:255'], 'lastName' => ['sometimes', 'string', 'max:255'], 'phone' => ['sometimes', 'nullable', 'string', 'max:255'], 'preferredLanguage' => ['sometimes', 'string', 'max:10'], 'isOwner' => ['sometimes', 'boolean'], 'isPrimary' => ['sometimes', 'boolean'], 'status' => ['sometimes', Rule::enum(UserStatus::class)], 'roleIds' => ['sometimes', 'array'], 'roleIds.*' => ['required', 'ulid', 'distinct']];
    }
}
