<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Commande réduite à ce qu'affiche une liste déroulante ou un rappel.
 *
 * @mixin Order
 */
class OrderCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'customerReference' => $this->customer_reference,
            'orderDate' => $this->order_date,
            'status' => $this->status?->value,
        ];
    }
}
