<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Addresses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
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
            'code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'addressLine1' => ['sometimes', 'string', 'max:255'],
            'addressLine2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'addressLine3' => ['sometimes', 'nullable', 'string', 'max:255'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:64'],
            'addressNumber' => ['sometimes', 'nullable', 'string', 'max:64'],
            'route' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sublocality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'town' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'timeWindowFrom' => ['sometimes', 'nullable', 'date_format:H:i'],
            'timeWindowTo' => ['sometimes', 'nullable', 'date_format:H:i', 'after_or_equal:timeWindowFrom'],
            'isDefault' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
