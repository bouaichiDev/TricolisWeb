<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Stock;

use App\Modules\CustomerPortal\Services\Stock\StockQuantity;
use App\Modules\Stock\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La quantité d'un article du client à un emplacement (§11) — lecture seule.
 *
 * @mixin StockBalance
 */
class CustomerStockBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location' => CustomerStockLocation::present($this->stockLocation),
            ...StockQuantity::totals($this->quantity, $this->reserved_quantity, $this->available_quantity),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
