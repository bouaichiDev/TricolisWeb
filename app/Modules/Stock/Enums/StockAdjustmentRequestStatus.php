<?php

declare(strict_types=1);

namespace App\Modules\Stock\Enums;

/**
 * Cycle d'une demande de correction de stock : déposée par le client,
 * tranchée une fois par le transporteur.
 *
 * Trois états, pas davantage : une demande attend, est acceptée — le
 * mouvement de correction est alors créé — ou est refusée. Aucun retour en
 * arrière : une décision rendue ne se rejoue pas.
 */
enum StockAdjustmentRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::APPROVED => 'Acceptée',
            self::REJECTED => 'Refusée',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::PENDING;
    }
}
