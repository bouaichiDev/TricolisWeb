<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Fleet;

use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vehicle */
class VehicleCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'registrationNumber' => $this->registration_number,
            'status' => $this->status,
        ];
    }
}
