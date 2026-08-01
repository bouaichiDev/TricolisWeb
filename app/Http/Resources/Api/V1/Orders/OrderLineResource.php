<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use App\Modules\Orders\Models\OrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderLine */
class OrderLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'catalogItemId' => $this->catalog_item_id,
            'parentLineId' => $this->parent_line_id,
            'externalReference' => $this->external_reference,
            'articleCode' => $this->article_code,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'reservedQuantity' => $this->reserved_quantity,
            'preparedQuantity' => $this->prepared_quantity,
            'deliveredQuantity' => $this->delivered_quantity,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'purchasePrice' => $this->purchase_price,
            'sellingPrice' => $this->selling_price,
            'status' => $this->status,
            'fromCatalog' => $this->catalog_item_id !== null,
        ];
    }
}
