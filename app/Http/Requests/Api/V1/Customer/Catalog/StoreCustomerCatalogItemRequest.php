<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Catalog;

use App\Http\Requests\Api\V1\Customer\ServerControlledFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Un article ajouté au catalogue depuis le portail : les champs de l'ajout
 * interne (`StoreCatalogItemRequest`), moins ce que le transporteur garde —
 * le statut, le client, l'organisation.
 */
class StoreCustomerCatalogItemRequest extends FormRequest
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
            ...ServerControlledFields::prohibit([...ServerControlledFields::TENANT, 'status']),
            'catalogId' => ['required', 'string', 'ulid'],
            'articleCode' => ['required', 'string', 'max:128'],
            'barcode' => ['nullable', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
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
