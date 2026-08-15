<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\DTOs\CreateStockReservationData;
use App\Modules\Stock\Models\StockReservation;
use App\Modules\Stock\Services\StockBalanceLocker;
use App\Modules\Stock\Services\StockScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Réserve du stock pour une ligne de commande.
 *
 * Séquence du §23 : verrouiller le solde, contrôler `availableQuantity`,
 * augmenter `reservedQuantity`, dériver `availableQuantity`, créer la
 * réservation — le tout dans une transaction.
 *
 * Le verrou est ce qui rend le contrôle fiable : sans lui, deux réservations
 * concurrentes liraient la même disponibilité et la consommeraient chacune
 * intégralement.
 *
 * La ligne de commande doit venir d'une commande du client de l'article : on ne
 * réserve pas le stock d'un client pour la commande d'un autre.
 */
final readonly class CreateStockReservationAction
{
    public function __construct(
        private StockScopeGuard $guard,
        private StockBalanceLocker $locker,
        private RecalculateStockBalance $balances,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateStockReservationData $data, AuditContext $context, string $now): StockReservation
    {
        $item = $this->guard->stockItem($data->stockItemId, $context->organizationId);
        $location = $this->guard->stockLocation($data->stockLocationId, $context->organizationId);
        $this->guard->orderLine($data->orderLineId, $item);

        return DB::transaction(function () use ($data, $item, $location, $context, $now): StockReservation {
            $balance = $this->locker->lockOrCreate($item, $location, $now);

            $this->balances->assertAvailable($balance, $data->quantity);
            $this->balances->execute($balance, '0', $data->quantity, $now);

            $reservation = StockReservation::create($data->toAttributes($now));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'stock_reservation.created',
                $reservation,
                null,
                $reservation->only(['stock_item_id', 'stock_location_id', 'order_line_id', 'quantity', 'status']),
                null,
                $context->ipAddress,
            );

            return $reservation;
        });
    }
}
