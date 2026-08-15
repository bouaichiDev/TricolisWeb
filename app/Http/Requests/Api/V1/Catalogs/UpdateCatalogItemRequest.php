<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Catalogs;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogItemRequest extends FormRequest
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
            'articleCode' => ['sometimes', 'string', 'max:128'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:128'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'weight' => ['sometimes', 'numeric', 'min:0'],
            'volume' => ['sometimes', 'numeric', 'min:0'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
