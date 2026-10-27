<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Http\Resources\Api\V1\Customer\Stock\CustomerStockLocation;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Services\StockAdjustmentQuantities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une demande de correction, côté transporteur.
 *
 * `status` est le **code** : le libellé se lit dans le référentiel des
 * statuts (source `stock_adjustment_request`), comme partout en interne.
 * L'emplacement est présenté comme au client — dépôt, zone, allée, rack,
 * niveau, code — pour que les deux parties parlent du même endroit.
 *
 * @mixin StockAdjustmentRequest
 */
class StockAdjustmentRequestListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'customer' => $this->customer === null ? null : ['id' => $this->customer->id, 'code' => $this->customer->code, 'name' => $this->customer->name],
            'stockItemId' => $this->stock_item_id,
            'stockItem' => $this->stockItem === null ? null : ['id' => $this->stockItem->id, 'articleCode' => $this->stockItem->article_code, 'description' => $this->stockItem->description],
            'stockLocationId' => $this->stock_location_id,
            'location' => CustomerStockLocation::present($this->stockLocation),
            'displayedQuantity' => $this->displayed_quantity,
            'requestedQuantity' => $this->requested_quantity,
            'difference' => app(StockAdjustmentQuantities::class)->difference($this->resource),
            'reason' => $this->reason,
            'comment' => $this->comment,
            'status' => $this->status->value,
            'requester' => $this->requester === null ? null : ['id' => $this->requester->id, 'name' => $this->requester->fullName()],
            'reviewer' => $this->reviewer === null ? null : ['id' => $this->reviewer->id, 'name' => $this->reviewer->fullName()],
            'reviewedAt' => $this->reviewed_at?->toIso8601String(),
            'reviewComment' => $this->review_comment,
            'stockMovementId' => $this->stock_movement_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
