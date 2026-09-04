<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Catalogs;

use App\Modules\Catalogs\Models\CustomerCatalogItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerCatalogItem */
class CatalogItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'catalogId' => $this->catalog_id,
            'articleCode' => $this->article_code,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'assemblyTimeMinutes' => $this->assembly_time_minutes,
            'status' => $this->status,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
