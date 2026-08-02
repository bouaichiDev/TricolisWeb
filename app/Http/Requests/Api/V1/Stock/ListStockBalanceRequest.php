<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use App\Shared\Http\Requests\ListRequest;

class ListStockBalanceRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'stockItemId' => ['sometimes', 'ulid'],
            'stockLocationId' => ['sometimes', 'ulid'],
            'customerId' => ['sometimes', 'ulid'],
            'availableOnly' => ['sometimes', 'boolean'],
        ]);
    }
}
