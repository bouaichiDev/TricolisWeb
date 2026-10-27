<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Services\StockAdjustmentQuantities;
use App\Modules\Stock\Services\StockAdjustmentSettings;
use Illuminate\Http\Request;

/**
 * Fiche d'une demande de correction : la liste, plus ce qu'il faut pour
 * trancher.
 *
 * ```text
 * currentQuantity  la balance aujourd'hui (emplacement, ou total article)
 * drift            currentQuantity − displayedQuantity : a-t-il bougé depuis ?
 * movementType     le type que l'acceptation donnera au mouvement (réglage)
 * corrections      le mouvement que l'acceptation créerait, par emplacement
 * actions          calculées ici — l'écran ne les recalcule jamais
 * ```
 *
 * @mixin StockAdjustmentRequest
 */
class StockAdjustmentRequestDetailResource extends StockAdjustmentRequestListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quantities = app(StockAdjustmentQuantities::class);
        $current = $quantities->current($this->resource);
        $reviewable = $this->isPending() && ($request->user()?->can('review', $this->resource) ?? false);
        $movement = $this->stockMovement;

        return [
            ...parent::toArray($request),
            'currentQuantity' => $current,
            'drift' => $quantities->drift($this->resource, $current),
            'movementType' => app(StockAdjustmentSettings::class)->movementType($this->organization_id),
            'stockMovement' => $movement === null ? null : [
                'id' => $movement->id,
                'movementType' => $movement->movement_type,
                'quantity' => $movement->quantity,
                'sourceLocationId' => $movement->source_location_id,
                'destinationLocationId' => $movement->destination_location_id,
                'createdAt' => $movement->created_at?->toIso8601String(),
            ],
            // Le récapitulatif du dialogue d'acceptation, par emplacement
            // possible : une demande tranchée n'en a plus besoin.
            'corrections' => $this->isPending() ? $quantities->corrections($this->resource) : [],
            'actions' => ['canApprove' => $reviewable, 'canReject' => $reviewable],
        ];
    }
}
