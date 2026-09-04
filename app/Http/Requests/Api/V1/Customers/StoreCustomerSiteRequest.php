<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customers;

use App\Modules\Addresses\Models\Address;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'addressId' => [
                'required', 'ulid',
                new BelongsToActiveOrganization(Address::class, 'entityAddresses', 'Cette adresse n’appartient pas à l’organisation active.'),
            ],
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'siteType' => ['nullable', 'string', 'max:64'],
            'isDefault' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
