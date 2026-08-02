<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockBalance;
use App\Shared\Support\Money;

/**
 * Applique une variation de quantité ou de réservation à un solde, puis dérive
 * `availableQuantity`.
 *
 * Point unique d'écriture des trois quantités. Le §13 exige de centraliser le
 * calcul et de ne jamais faire confiance à une valeur fournie :
 *
 * ```text
 * availableQuantity = quantity − reservedQuantity
 * ```
 *
 * Trois invariants sont vérifiés **avant** écriture, jamais après :
 *
 * ```text
 * quantity         >= 0
 * reservedQuantity >= 0
 * reservedQuantity <= quantity
 * ```
 *
 * Le troisième est le plus important : il interdit de sortir du stock déjà
 * réservé pour une commande.
 */
final readonly class RecalculateStockBalance
{
    public function execute(StockBalance $balance, string $quantityDelta, string $reservedDelta, string $now): StockBalance
    {
        $quantity = Money::round(Money::add((string) $balance->quantity, $quantityDelta), 3);
        $reserved = Money::round(Money::add((string) $balance->reserved_quantity, $reservedDelta), 3);

        if (bccomp($quantity, '0', 3) < 0) {
            throw StockConflict::insufficientQuantity();
        }

        if (bccomp($reserved, '0', 3) < 0) {
            throw StockConflict::insufficientReservation();
        }

        if (bccomp($reserved, $quantity, 3) > 0) {
            throw StockConflict::reservedExceedsQuantity();
        }

        $balance->update([
            'quantity' => $quantity,
            'reserved_quantity' => $reserved,
            'available_quantity' => Money::round(Money::subtract($quantity, $reserved), 3),
            'updated_at' => $now,
        ]);

        return $balance->refresh();
    }

    /**
     * La quantité demandée est-elle disponible ?
     *
     * Lu sous verrou, donc fiable : aucune autre transaction ne peut avoir
     * modifié le solde entre cette lecture et l'écriture qui suit.
     */
    public function assertAvailable(StockBalance $balance, string $quantity): void
    {
        if (bccomp((string) $balance->available_quantity, $quantity, 3) < 0) {
            throw StockConflict::insufficientAvailability(
                (string) $balance->available_quantity,
                $quantity,
            );
        }
    }
}
