<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\DTOs\UpdateStockReservationData;
use App\Modules\Stock\Models\StockReservation;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie le statut d'une réservation.
 *
 * Rien d'autre n'est modifiable : le §24 limite `PATCH` aux champs réellement
 * modifiables et interdit de changer la quantité sans Action dédiée. Une
 * quantité modifiée ici devrait ajuster le solde sous verrou — c'est une
 * opération de stock, pas une correction de saisie. Pour réserver autrement, on
 * libère puis on recrée.
 */
final readonly class UpdateStockReservationAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(StockReservation $reservation, UpdateStockReservationData $data, AuditContext $context): StockReservation
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $reservation;
        }

        return DB::transaction(function () use ($reservation, $attributes, $context): StockReservation {
            $before = $reservation->only(array_keys($attributes));
            $reservation->update($attributes);
            $after = $reservation->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'stock_reservation.updated',
                    $reservation,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $reservation->fresh();
        });
    }
}
