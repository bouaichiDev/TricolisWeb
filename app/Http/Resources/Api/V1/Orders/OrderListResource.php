<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Commande vue depuis une liste.
 *
 * Volontairement plate : ni lignes, ni colis, ni services. Les charger pour une
 * liste de 25 commandes produirait des centaines de lignes inutiles.
 *
 * @mixin Order
 */
class OrderListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'externalReference' => $this->external_reference,
            'customerReference' => $this->customer_reference,
            'customerId' => $this->customer_id,
            'agencyId' => $this->agency_id,
            'depotId' => $this->depot_id,
            'orderType' => $this->order_type,
            'orderDate' => $this->order_date,
            'source' => $this->source?->value,
            'status' => $this->status?->value,
            'statusLabel' => $this->status?->label(),
            'weight' => $this->weight,
            'volume' => $this->volume,
            'packageCount' => $this->package_count,
            'currencyCode' => $this->currency_code,
            'customerName' => $this->whenLoaded('customer', fn () => $this->customer->name),
            'agencyName' => $this->whenLoaded('agency', fn () => $this->agency->name),
            'lineCount' => $this->whenCounted('lines'),
            'serviceCount' => $this->whenCounted('orderServices'),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
