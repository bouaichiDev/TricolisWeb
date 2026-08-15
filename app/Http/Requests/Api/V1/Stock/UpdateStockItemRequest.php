<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockItemRequest extends FormRequest
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
        $item = $this->route('stockItem');
        $customerId = $item?->customer_id;

        return [
            'catalogItemId' => ['sometimes', 'nullable', 'ulid'],
            'articleCode' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('stock_items', 'article_code')->where('customer_id', $customerId)->ignore($item?->id),
            ],
            'barcode' => [
                'sometimes', 'nullable', 'string', 'max:128',
                Rule::unique('stock_items', 'barcode')->where('customer_id', $customerId)->ignore($item?->id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
