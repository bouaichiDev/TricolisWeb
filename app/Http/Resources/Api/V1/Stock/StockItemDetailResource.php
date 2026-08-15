<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Http\Resources\Api\V1\Customers\CustomerCompactResource;
use App\Modules\Stock\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'un article de stock.
 *
 * Les soldes ne sont restitués que s'ils ont été explicitement chargés.
 *
 * @mixin StockItem
 */
class StockItemDetailResource extends JsonResource
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
            'customer' => new CustomerCompactResource($this->whenLoaded('customer')),
            'balances' => StockBalanceListResource::collection($this->whenLoaded('balances')),
        ];
    }
}
