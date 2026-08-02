<?php

declare(strict_types=1);

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Obtient le solde d'un couple article + emplacement, **verrouillé**.
 *
 * Le §27 impose le verrouillage pessimiste pour les mouvements et les
 * réservations. Sans lui, deux réservations concurrentes sur le même solde
 * liraient la même `availableQuantity` et réserveraient chacune la totalité :
 * le stock partirait deux fois.
 *
 * Le verrou tient jusqu'à la fin de la transaction. C'est le même mécanisme que
 * `GenerateOrderNumber` et l'allocation des quantités de colis, en Phase 2.
 */
final readonly class StockBalanceLocker
{
    public function lockOrCreate(StockItem $item, StockLocation $location, string $now): StockBalance
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Le solde de stock doit être verrouillé dans une transaction.');
        }

        $balance = $this->lock($item->id, $location->id);

        if ($balance !== null) {
            return $balance;
        }

        try {
            return StockBalance::create([
                'stock_item_id' => $item->id,
                'stock_location_id' => $location->id,
                'quantity' => 0,
                'reserved_quantity' => 0,
                'available_quantity' => 0,
                'updated_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Deux transactions ont pu arriver ici en meme temps : la contrainte
            // unique arbitre, et le perdant relit la ligne du gagnant sous verrou.
            return $this->lock($item->id, $location->id)
                ?? throw new RuntimeException('Solde de stock introuvable après conflit d’unicité.');
        }
    }

    private function lock(string $itemId, string $locationId): ?StockBalance
    {
        return StockBalance::query()
            ->where('stock_item_id', $itemId)
            ->where('stock_location_id', $locationId)
            ->lockForUpdate()
            ->first();
    }
}
