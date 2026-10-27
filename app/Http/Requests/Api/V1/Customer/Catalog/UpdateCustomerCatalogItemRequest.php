<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Catalog;

use App\Http\Requests\Api\V1\Customer\ServerControlledFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Un article modifié depuis le portail : les champs de la modification
 * interne (`UpdateCatalogItemRequest`), moins ce qui rattache l'article ou le
 * décide — client, organisation, catalogue, statut. Un article ne change
 * jamais de catalogue, donc jamais de client ; son statut passe par
 * `activate` / `deactivate`.
 */
class UpdateCustomerCatalogItemRequest extends FormRequest
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
        return [
            ...ServerControlledFields::prohibit([...ServerControlledFields::TENANT, 'status', 'catalogId', 'catalog_id', 'id']),
            'articleCode' => ['sometimes', 'string', 'max:128'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:128'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'volume' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'assemblyTimeMinutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ServerControlledFields::messages();
    }
}
