<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un emplacement.
 *
 * `zoneCode`, `aisle`, `rack` et `level` restent des chaînes libres : le §9
 * interdit une table `StockZone`.
 */
class StoreStockLocationRequest extends FormRequest
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
        $depotId = $this->input('depotId');

        return [
            'depotId' => ['required', 'ulid'],
            'parentLocationId' => ['nullable', 'ulid'],
            'zoneCode' => ['nullable', 'string', 'max:64'],
            'aisle' => ['nullable', 'string', 'max:32'],
            'rack' => ['nullable', 'string', 'max:32'],
            'level' => ['nullable', 'string', 'max:32'],
            'locationCode' => [
                'required', 'string', 'max:64',
                Rule::unique('stock_locations', 'location_code')->where('depot_id', $depotId),
            ],
            'barcode' => [
                'nullable', 'string', 'max:128',
                Rule::unique('stock_locations', 'barcode')->where('depot_id', $depotId),
            ],
            'status' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locationCode.unique' => 'Ce code d’emplacement existe déjà dans ce dépôt.',
            'barcode.unique' => 'Ce code-barres existe déjà dans ce dépôt.',
        ];
    }
}
