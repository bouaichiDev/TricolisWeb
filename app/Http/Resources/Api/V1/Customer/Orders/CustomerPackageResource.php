<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Orders;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Modules\Packages\Models\Package;
use App\Shared\Database\MorphMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un colis, sans son emplacement de stock (donnée d'entrepôt interne).
 *
 * `lines` donne la répartition des lignes de commande dans le colis.
 *
 * @mixin Package
 */
class CustomerPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parentPackageId' => $this->parent_package_id,
            'packageType' => $this->whenLoaded('packageType', fn () => $this->packageType ? ['id' => $this->packageType->id, 'name' => $this->packageType->name] : null),
            'groupingType' => $this->whenLoaded('groupingType', fn () => $this->groupingType ? ['id' => $this->groupingType->id, 'name' => $this->groupingType->name] : null),
            'barcode' => $this->barcode,
            'reference' => $this->reference,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'status' => app(CustomerStatusLabels::class)->present(MorphMap::PACKAGE, $this->status),
            'lines' => $this->whenLoaded('packageOrderLines', fn () => $this->packageOrderLines->map(static fn ($allocation): array => [
                'orderLineId' => $allocation->order_line_id,
                'quantity' => $allocation->quantity,
            ])->values()->all()),
        ];
    }
}
