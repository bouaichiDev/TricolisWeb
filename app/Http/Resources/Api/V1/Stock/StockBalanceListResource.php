<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Modules\Stock\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Solde d'un article à un emplacement.
 *
 * `availableQuantity` est toujours `quantity − reservedQuantity` : elle est
 * dérivée à chaque écriture, jamais fournie.
 *
 * @mixin StockBalance
 */
class StockBalanceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stockItemId' => $this->stock_item_id,
            'stockLocationId' => $this->stock_location_id,
            'quantity' => $this->quantity,
            'reservedQuantity' => $this->reserved_quantity,
            'availableQuantity' => $this->available_quantity,
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'articleCode' => $this->whenLoaded('stockItem', fn () => $this->stockItem->article_code),
            'locationCode' => $this->whenLoaded('stockLocation', fn () => $this->stockLocation->location_code),
        ];
    }
}
