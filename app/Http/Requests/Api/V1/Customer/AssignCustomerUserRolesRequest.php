<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class AssignCustomerUserRolesRequest extends FormRequest
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
            ...ServerControlledFields::prohibit([...ServerControlledFields::TENANT, 'permissions', 'scope', 'isAdmin', 'isOwner', 'is_owner']),
            'roleIds' => ['present', 'array', 'max:20'],
            'roleIds.*' => ['string', 'ulid'],
        ];
    }
}
