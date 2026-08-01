<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\ProviderSettlements;

use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne de décompte fournisseur.
 *
 * Sept champs, pas un de plus : ni taxe, ni statut, ni date de service.
 *
 * @mixin ProviderSettlementLine
 */
class ProviderSettlementLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'settlementId' => $this->settlement_id,
            'orderServiceId' => $this->order_service_id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitCost' => $this->unit_cost,
            'totalCost' => $this->total_cost,
        ];
    }
}
