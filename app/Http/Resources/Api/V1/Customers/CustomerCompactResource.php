<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCompactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'status' => $this->status?->value];
    }
}
