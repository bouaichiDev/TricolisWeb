<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Addresses;

use App\Shared\Database\MorphMap;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddressRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:255'],
            'addressLine1' => ['required', 'string', 'max:255'],
            'addressLine2' => ['nullable', 'string', 'max:255'],
            'addressLine3' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:64'],
            'addressNumber' => ['nullable', 'string', 'max:64'],
            'route' => ['nullable', 'string', 'max:255'],
            'sublocality' => ['nullable', 'string', 'max:255'],
            'postalCode' => ['nullable', 'string', 'max:64'],
            'city' => ['nullable', 'string', 'max:255'],
            'town' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'instructions' => ['nullable', 'string'],
            'timeWindowFrom' => ['nullable', 'date_format:H:i'],
            'timeWindowTo' => ['nullable', 'date_format:H:i', 'after_or_equal:timeWindowFrom'],
            'isDefault' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'max:20'],
            'entityType' => ['nullable', 'string', Rule::in($this->allowedEntityTypes())],
            'entityId' => ['required_with:entityType', 'ulid'],
            'addressType' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedEntityTypes(): array
    {
        return [
            MorphMap::ORGANIZATION,
            MorphMap::CUSTOMER,
            MorphMap::CUSTOMER_SITE,
            MorphMap::AGENCY,
            MorphMap::DEPOT,
        ];
    }
}
