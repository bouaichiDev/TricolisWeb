<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use App\Http\Resources\Api\V1\Packages\PackageResource;
use App\Modules\Orders\Models\OrderServicePackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderServicePackage */
class OrderServicePackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderServiceId' => $this->order_service_id,
            'packageId' => $this->package_id,
            'quantity' => $this->quantity,
            'handlingInstructions' => $this->handling_instructions,
            'status' => $this->status,
            'package' => new PackageResource($this->whenLoaded('package')),
        ];
    }
}
