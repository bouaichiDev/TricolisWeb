<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use App\Shared\Http\Requests\ListRequest;

/**
 * `legacyId` n'y figure pas : la colonne n'existe pas — voir
 * `phase-7-analysis.md` §1.
 */
class ListStockLocationRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'depotId' => ['sometimes', 'ulid'],
            'parentLocationId' => ['sometimes', 'ulid'],
            'zoneCode' => ['sometimes', 'string', 'max:64'],
            'aisle' => ['sometimes', 'string', 'max:32'],
            'rack' => ['sometimes', 'string', 'max:32'],
            'level' => ['sometimes', 'string', 'max:32'],
            'locationCode' => ['sometimes', 'string', 'max:64'],
            'barcode' => ['sometimes', 'string', 'max:128'],
        ]);
    }
}
