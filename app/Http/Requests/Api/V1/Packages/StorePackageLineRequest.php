<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Packages;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageLineRequest extends FormRequest
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
            'orderLineId' => ['required', 'ulid'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
