<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\DTOs\ReleaseStockReservationData;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockReservation;
use App\Modules\Stock\Services\StockBalanceLocker;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Libère une réservation et rend la quantité disponible.
 *
 * **La réservation n'est pas supprimée** : `releasedAt` est renseigné, la ligne
 * reste. Le §23 l'exige — c'est ce qui permet de retracer ce qui a été
 * immobilisé puis relâché.
 *
 * **Aucune double libération.** Sans ce refus, appeler la route deux fois
 * décrémenterait `reservedQuantity` deux fois et libérerait du stock qui n'avait
 * jamais été réservé.
 *
 * Le statut est fourni et validé, jamais inventé : le diagramme n'en énumère
 * aucun.
 */
final readonly class ReleaseStockReservationAction
{
    public function __construct(
        private StockBalanceLocker $locker,
        private RecalculateStockBalance $balances,
        private WriteAuditLog $audit,
    ) {}

    public function execute(StockReservation $reservation, ReleaseStockReservationData $data, AuditContext $context, string $now): StockReservation
    {
        if ($reservation->isReleased()) {
            throw StockConflict::alreadyReleased();
        }

        return DB::transaction(function () use ($reservation, $data, $context, $now): StockReservation {
            // Relecture sous verrou : entre le controle ci-dessus et ici, une
            // autre transaction a pu liberer la meme reservation.
            $locked = StockReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->isReleased()) {
                throw StockConflict::alreadyReleased();
            }

            $balance = $this->locker->lockOrCreate($locked->stockItem, $locked->stockLocation, $now);
            $this->balances->execute($balance, '0', '-'.$locked->quantity, $now);

            $locked->update([
                'status' => $data->status,
                'released_at' => $now,
            ]);

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'stock_reservation.released',
                $locked,
                ['released_at' => null],
                ['released_at' => $now, 'status' => $data->status],
                null,
                $context->ipAddress,
            );

            return $locked->fresh();
        });
    }
}
