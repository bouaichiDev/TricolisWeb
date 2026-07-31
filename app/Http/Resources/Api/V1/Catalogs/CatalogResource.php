<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Catalogs;

use App\Modules\Catalogs\Models\CustomerCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerCatalog */
class CatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'itemCount' => $this->whenCounted('items'),
            'items' => CatalogItemResource::collection($this->whenLoaded('items')),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
