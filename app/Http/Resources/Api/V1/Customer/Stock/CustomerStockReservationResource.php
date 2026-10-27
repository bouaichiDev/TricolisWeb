<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Stock;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Modules\CustomerPortal\Services\Stock\StockQuantity;
use App\Modules\Stock\Models\StockReservation;
use App\Shared\Database\MorphMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une réservation d'un article du client pour une de ses commandes (§17),
 * en lecture seule. La ligne n'expose ni prix ni quantités internes de
 * préparation : article, désignation, quantité commandée.
 *
 * @mixin StockReservation
 */
class CustomerStockReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $line = $this->orderLine;

        return [
            'id' => $this->id,
            'stockItem' => ['id' => $this->stockItem?->id, 'articleCode' => $this->stockItem?->article_code, 'description' => $this->stockItem?->description],
            'location' => CustomerStockLocation::present($this->stockLocation),
            'quantity' => StockQuantity::of($this->quantity),
            'status' => app(CustomerStatusLabels::class)->present(MorphMap::STOCK_RESERVATION, $this->status),
            'isReleased' => $this->isReleased(),
            'reservedAt' => $this->reserved_at?->toIso8601String(),
            'releasedAt' => $this->released_at?->toIso8601String(),
            'order' => $line?->order === null ? null : ['id' => $line->order->id, 'reference' => $line->order->order_number, 'customerReference' => $line->order->customer_reference],
            'orderLine' => $line === null ? null : ['id' => $line->id, 'articleCode' => $line->article_code, 'name' => $line->name, 'quantity' => StockQuantity::of($line->quantity)],
        ];
    }
}
