<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Addresses;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntityAddressRequest extends FormRequest
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
            'entityType' => ['required', 'string', 'max:64'],
            'entityId' => ['required', 'ulid'],
            'addressType' => ['nullable', 'string', 'max:64'],
            'isDefault' => ['sometimes', 'boolean'],
        ];
    }
}
