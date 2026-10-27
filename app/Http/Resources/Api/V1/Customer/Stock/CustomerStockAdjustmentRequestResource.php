<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Stock;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Modules\CustomerPortal\Services\Stock\StockQuantity;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Shared\Database\MorphMap;
use App\Shared\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une demande de correction du client, et la réponse du transporteur.
 *
 * `reviewComment` est la réponse adressée au client ; qui l'a rendue, en
 * interne, n'est pas exposé. `difference` est calculé en bcmath : constaté
 * moins affiché.
 *
 * @mixin StockAdjustmentRequest
 */
class CustomerStockAdjustmentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $displayed = StockQuantity::of($this->displayed_quantity);
        $requested = StockQuantity::of($this->requested_quantity);

        return [
            'id' => $this->id,
            'stockItem' => ['id' => $this->stockItem?->id, 'articleCode' => $this->stockItem?->article_code, 'description' => $this->stockItem?->description],
            'location' => CustomerStockLocation::present($this->stockLocation),
            'displayedQuantity' => $displayed,
            'requestedQuantity' => $requested,
            'difference' => Money::round(Money::subtract($requested, $displayed), 3),
            'reason' => $this->reason,
            'comment' => $this->comment,
            'status' => app(CustomerStatusLabels::class)->present(MorphMap::STOCK_ADJUSTMENT_REQUEST, $this->status),
            'isPending' => $this->isPending(),
            'reviewComment' => $this->review_comment,
            'reviewedAt' => $this->reviewed_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
