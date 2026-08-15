<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Stock;

use App\Shared\Http\Requests\ListRequest;

class ListStockMovementRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'stockItemId' => ['sometimes', 'ulid'],
            'sourceLocationId' => ['sometimes', 'ulid'],
            'destinationLocationId' => ['sometimes', 'ulid'],
            'movementType' => ['sometimes', 'string', 'max:64'],
            'sourceEntityType' => ['sometimes', 'string', 'max:64'],
            'sourceEntityId' => ['sometimes', 'ulid'],
            'createdBy' => ['sometimes', 'ulid'],
        ]);
    }
}
