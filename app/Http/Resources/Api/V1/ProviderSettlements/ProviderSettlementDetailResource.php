<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\ProviderSettlements;

use App\Http\Resources\Api\V1\Providers\ProviderCompactResource;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Détail d'un décompte fournisseur.
 *
 * @mixin ProviderSettlement
 */
class ProviderSettlementDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'providerId' => $this->provider_id,
            'settlementNumber' => $this->settlement_number,
            'periodFrom' => $this->period_from?->toDateString(),
            'periodTo' => $this->period_to?->toDateString(),
            'subtotal' => $this->subtotal,
            'taxTotal' => $this->tax_total,
            'total' => $this->total,
            'status' => $this->status,
            'provider' => new ProviderCompactResource($this->whenLoaded('provider')),
            'lines' => ProviderSettlementLineResource::collection($this->whenLoaded('lines')),
            'lineCount' => $this->whenCounted('lines'),
        ];
    }
}
