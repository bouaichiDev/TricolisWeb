<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un emplacement.
 *
 * `depotId` n'est pas modifiable : déplacer un emplacement d'un dépôt à l'autre
 * casserait sa hiérarchie et l'unicité de son code.
 */
class UpdateStockLocationRequest extends FormRequest
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
        $location = $this->route('stockLocation');
        $depotId = $location?->depot_id;

        return [
            'parentLocationId' => ['sometimes', 'nullable', 'ulid'],
            'zoneCode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'aisle' => ['sometimes', 'nullable', 'string', 'max:32'],
            'rack' => ['sometimes', 'nullable', 'string', 'max:32'],
            'level' => ['sometimes', 'nullable', 'string', 'max:32'],
            'locationCode' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('stock_locations', 'location_code')->where('depot_id', $depotId)->ignore($location?->id),
            ],
            'barcode' => [
                'sometimes', 'nullable', 'string', 'max:128',
                Rule::unique('stock_locations', 'barcode')->where('depot_id', $depotId)->ignore($location?->id),
            ],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
