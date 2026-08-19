<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Packages;

use App\Modules\Packages\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Package */
class PackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'parentPackageId' => $this->parent_package_id,
            'packageTypeId' => $this->package_type_id,
            'groupingTypeId' => $this->grouping_type_id,
            // Colonne du diagramme, presente en base des la creation de la
            // table : l'omettre de la ressource la rendait invisible cote
            // client alors qu'elle est renseignee par le module Stock.
            'currentStockLocationId' => $this->current_stock_location_id,
            'barcode' => $this->barcode,
            'reference' => $this->reference,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'status' => $this->status,
            'packageType' => new ReferentialResource($this->whenLoaded('packageType')),
            'groupingType' => new ReferentialResource($this->whenLoaded('groupingType')),
            'lines' => $this->whenLoaded('packageOrderLines', fn () => $this->packageOrderLines->map(fn ($allocation): array => [
                'id' => $allocation->id,
                'orderLineId' => $allocation->order_line_id,
                'quantity' => $allocation->quantity,
            ])),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
