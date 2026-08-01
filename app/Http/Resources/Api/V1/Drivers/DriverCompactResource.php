<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Drivers;

use App\Modules\Drivers\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Driver */
class DriverCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
