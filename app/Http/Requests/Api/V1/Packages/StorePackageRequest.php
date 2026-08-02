<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Packages;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
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
            'parentPackageId' => ['nullable', 'ulid'],
            'packageTypeId' => ['nullable', 'ulid'],
            'groupingTypeId' => ['nullable', 'ulid'],
            'barcode' => ['nullable', 'string', 'max:128'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'weight' => ['sometimes', 'numeric', 'min:0'],
            'volume' => ['sometimes', 'numeric', 'min:0'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
