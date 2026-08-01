<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\ProviderSettlements;

use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Décompte réduit à ce qu'affiche un rappel.
 *
 * @mixin ProviderSettlement
 */
class ProviderSettlementCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'settlementNumber' => $this->settlement_number,
            'total' => $this->total,
            'status' => $this->status,
        ];
    }
}
