<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * L'état d'une demande d'accès.
 *
 * Trois états, et un seul chemin : une demande en attente est acceptée ou
 * refusée, et une décision ne se reprend pas. Rouvrir une demande déjà tranchée
 * laisserait créer deux organisations depuis la même ligne.
 */
enum AccessRequestStatus: string
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

    public function isDecided(): bool
    {
        return $this !== self::PENDING;
    }
}
