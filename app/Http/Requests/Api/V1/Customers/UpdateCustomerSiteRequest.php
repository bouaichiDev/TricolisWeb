<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerSiteRequest extends FormRequest
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
            'addressId' => ['sometimes', 'string', Rule::exists('addresses', 'id')],
            'code' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:255'],
            'siteType' => ['sometimes', 'nullable', 'string', 'max:64'],
            'isDefault' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
