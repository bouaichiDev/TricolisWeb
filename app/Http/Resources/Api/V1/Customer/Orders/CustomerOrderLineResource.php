<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Orders;

use App\Modules\Orders\Models\OrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une ligne de commande, sans prix d'achat ni de vente.
 *
 * @mixin OrderLine
 */
class CustomerOrderLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'catalogItemId' => $this->catalog_item_id,
            'articleCode' => $this->article_code,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'deliveredQuantity' => $this->delivered_quantity,
        ];
    }
}
