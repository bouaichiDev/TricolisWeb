<?php

declare(strict_types=1);

namespace App\Modules\Stock\Services;

use App\Http\Resources\Api\V1\Customer\Stock\CustomerStockLocation;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockBalance;
use App\Shared\Support\Money;

/**
 * Ce que le transporteur lit sur une demande de correction : l'écart déclaré
 * par le client, et combien le stock a bougé depuis qu'il l'a déclaré.
 *
 * ```text
 * difference = requested − displayed   ce que le client a constaté
 * drift      = current   − displayed   le stock a-t-il bougé depuis ?
 * ```
 *
 * `current` se lit comme `displayed` a été figé : la balance de l'emplacement
 * de la demande, ou la somme de toutes les balances de l'article pour une
 * demande « tout l'article ». Tout en bcmath, trois décimales.
 */
final readonly class StockAdjustmentQuantities
{
    public function current(StockAdjustmentRequest $request): string
    {
        $balances = StockBalance::where('stock_item_id', $request->stock_item_id)
            ->when($request->stock_location_id !== null, static fn ($query) => $query->where('stock_location_id', $request->stock_location_id));

        // Somme SQL brute : `sum()` d'Eloquent la rendrait en flottant.
        $total = $balances->toBase()->selectRaw('SUM(quantity) AS total')->value('total');

        return Money::round((string) ($total ?? '0'), 3);
    }

    public function difference(StockAdjustmentRequest $request): string
    {
        return Money::round(Money::subtract((string) $request->requested_quantity, (string) $request->displayed_quantity), 3);
    }

    public function drift(StockAdjustmentRequest $request, string $current): string
    {
        return Money::round(Money::subtract($current, (string) $request->displayed_quantity), 3);
    }

    /**
     * Le mouvement qu'une acceptation créerait à chaque emplacement possible :
     * celui de la demande, ou — pour « tout l'article » — chaque emplacement où
     * l'article a une balance. Même calcul que l'acceptation : vers la quantité
     * constatée, depuis la quantité actuelle — de l'emplacement, ou le total
     * de l'article, qui est ce que le client a constaté.
     *
     * @return list<array<string, mixed>>
     */
    public function corrections(StockAdjustmentRequest $request): array
    {
        $delta = Money::round(Money::subtract((string) $request->requested_quantity, $this->current($request)), 3);
        $sign = bccomp($delta, '0', 3);

        return StockBalance::with(['stockLocation:id,depot_id,zone_code,aisle,rack,level,location_code', 'stockLocation.depot:id,name'])
            ->where('stock_item_id', $request->stock_item_id)
            ->when($request->stock_location_id !== null, static fn ($query) => $query->where('stock_location_id', $request->stock_location_id))
            ->orderBy('stock_location_id')
            ->get()
            ->map(static function (StockBalance $balance) use ($delta, $sign): array {
                return [
                    'location' => CustomerStockLocation::present($balance->stockLocation),
                    'currentQuantity' => Money::round((string) $balance->quantity, 3),
                    'reservedQuantity' => Money::round((string) $balance->reserved_quantity, 3),
                    'direction' => $sign === 0 ? 'none' : ($sign > 0 ? 'inbound' : 'outbound'),
                    'quantity' => ltrim($delta, '-'),
                ];
            })
            ->values()
            ->all();
    }
}
