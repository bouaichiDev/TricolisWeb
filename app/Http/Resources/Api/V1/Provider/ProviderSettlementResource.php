<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\ProviderPortal\Services\ProviderStatusLabels;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use App\Shared\Database\MorphMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un décompte clôturé, tel que son fournisseur le lit : période, montants
 * calculés par le module financier, statut du référentiel et, au détail, ses
 * lignes. Une ligne nomme le service par son **numéro** — jamais la commande,
 * le client ni le prix facturé au client.
 *
 * @mixin ProviderSettlement
 */
class ProviderSettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'settlementNumber' => $this->settlement_number,
            'periodFrom' => $this->period_from?->toDateString(),
            'periodTo' => $this->period_to?->toDateString(),
            'status' => $this->status,
            'statusLabel' => app(ProviderStatusLabels::class)->label(MorphMap::PROVIDER_SETTLEMENT, $this->status),
            'subtotal' => $this->subtotal,
            'taxTotal' => $this->tax_total,
            'total' => $this->total,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(static fn (ProviderSettlementLine $line): array => [
                'id' => $line->id,
                'serviceNumber' => $line->orderService?->service_number,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unitCost' => $line->unit_cost,
                'totalCost' => $line->total_cost,
            ])->values()),
        ];
    }
}
