<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajout d'un article en stock (§13) : un article, pas du stock physique.
 */
class StoreCustomerStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CustomerStockItemRules::rules(true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CustomerStockItemRules::messages();
    }
}
