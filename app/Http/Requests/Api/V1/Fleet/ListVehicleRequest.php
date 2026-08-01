<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Fleet;

use App\Shared\Http\Requests\ListRequest;

class ListVehicleRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'providerId' => ['sometimes', 'ulid'],
            'vehicleTypeId' => ['sometimes', 'ulid'],
            'payloadCapacityMin' => ['sometimes', 'numeric', 'min:0'],
            'volumeCapacityMin' => ['sometimes', 'numeric', 'min:0'],
            'palletCapacityMin' => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}
