<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'string', 'max:255'], 'scope' => ['sometimes', 'nullable', 'string', 'max:40'], 'status' => ['sometimes', 'string', 'max:20'], 'permissionIds' => ['sometimes', 'array'], 'permissionIds.*' => ['required', 'ulid', 'distinct', 'exists:permissions,id']];
    }
}
