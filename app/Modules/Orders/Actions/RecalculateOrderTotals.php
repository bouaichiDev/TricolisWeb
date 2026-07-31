<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Models\Order;

/**
 * Recalcule les agrégats d'une commande à partir de son contenu.
 *
 * `weight`, `volume` et `packageCount` existent au diagramme mais ne sont jamais
 * saisis : ils dérivent des lignes et des colis. Les recalculer à chaque
 * écriture évite qu'ils divergent silencieusement du contenu réel.
 */
final readonly class RecalculateOrderTotals
{
    public function execute(Order $order): Order
    {
        $lines = $order->lines()->get(['quantity', 'weight', 'volume']);

        $weight = $lines->sum(fn ($line): float => (float) $line->weight * (float) $line->quantity);
        $volume = $lines->sum(fn ($line): float => (float) $line->volume * (float) $line->quantity);

        $order->forceFill([
            'weight' => round($weight, 3),
            'volume' => round($volume, 4),
            'package_count' => $order->packages()->count(),
        ])->save();

        return $order;
    }
}
