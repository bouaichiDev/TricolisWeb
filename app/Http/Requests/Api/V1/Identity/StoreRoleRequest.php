<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'scope' => ['nullable', 'string', 'max:40'], 'isSystem' => ['required', 'boolean'], 'status' => ['required', 'string', 'max:20'], 'permissionIds' => ['sometimes', 'array'], 'permissionIds.*' => ['required', 'ulid', 'distinct', 'exists:permissions,id']];
    }
}
