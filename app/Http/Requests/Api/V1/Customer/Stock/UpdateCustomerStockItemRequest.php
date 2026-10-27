<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification des métadonnées d'un article (§14) : jamais sa quantité.
 */
class UpdateCustomerStockItemRequest extends FormRequest
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
        return CustomerStockItemRules::rules(false, (string) $this->route('stockItem'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CustomerStockItemRules::messages();
    }
}
