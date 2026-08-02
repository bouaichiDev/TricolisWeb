<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Modules\Stock\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Article vu depuis une liste : aucun solde, mouvement ni réservation chargé.
 *
 * @mixin StockItem
 */
class StockItemListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'catalogItemId' => $this->catalog_item_id,
            'articleCode' => $this->article_code,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'status' => $this->status,
            'customerName' => $this->whenLoaded('customer', fn () => $this->customer->name),
        ];
    }
}
