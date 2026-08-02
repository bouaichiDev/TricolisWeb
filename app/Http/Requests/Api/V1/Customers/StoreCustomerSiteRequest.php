<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerSiteRequest extends FormRequest
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
            'addressId' => ['required', 'string', Rule::exists('addresses', 'id')],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'siteType' => ['nullable', 'string', 'max:64'],
            'isDefault' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
