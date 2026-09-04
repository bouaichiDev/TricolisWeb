<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Catalogs;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'articleCode' => ['required', 'string', 'max:128'],
            'barcode' => ['nullable', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weight' => ['sometimes', 'numeric', 'min:0'],
            'volume' => ['sometimes', 'numeric', 'min:0'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'assemblyTimeMinutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
