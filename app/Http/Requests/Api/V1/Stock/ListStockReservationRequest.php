<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use App\Shared\Http\Requests\ListRequest;

class ListStockReservationRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'stockItemId' => ['sometimes', 'ulid'],
            'stockLocationId' => ['sometimes', 'ulid'],
            'orderLineId' => ['sometimes', 'ulid'],
            'reservedFrom' => ['sometimes', 'date'],
            'reservedTo' => ['sometimes', 'date', 'after_or_equal:reservedFrom'],
            'releasedFrom' => ['sometimes', 'date'],
            'releasedTo' => ['sometimes', 'date', 'after_or_equal:releasedFrom'],
        ]);
    }
}
