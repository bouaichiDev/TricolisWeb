<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use App\Shared\Http\Requests\ListRequest;

class ListStockItemRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'customerId' => ['sometimes', 'ulid'],
            'catalogItemId' => ['sometimes', 'ulid'],
            'articleCode' => ['sometimes', 'string', 'max:64'],
            'barcode' => ['sometimes', 'string', 'max:128'],
        ]);
    }
}
