<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un article de stock.
 *
 * Ni quantité ni emplacement : le §6 les interdit ici, le stock vit dans
 * `StockBalance`.
 */
class StoreStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sur `/customers/{customer}/stock-items`, le client vient de l'URL.
     */
    protected function prepareForValidation(): void
    {
        $customer = $this->route('customer');

        if ($customer !== null) {
            $this->merge(['customerId' => $customer->id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customerId = $this->input('customerId');

        return [
            'customerId' => ['required', 'ulid'],
            'catalogItemId' => ['nullable', 'ulid'],
            'articleCode' => [
                'required', 'string', 'max:64',
                Rule::unique('stock_items', 'article_code')->where('customer_id', $customerId),
            ],
            'barcode' => [
                'nullable', 'string', 'max:128',
                Rule::unique('stock_items', 'barcode')->where('customer_id', $customerId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'articleCode.unique' => 'Ce code article existe déjà chez ce client.',
            'barcode.unique' => 'Ce code-barres existe déjà chez ce client.',
        ];
    }
}
