<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Enums;

enum CustomerInvitationStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::ACCEPTED => 'Acceptée',
            self::EXPIRED => 'Expirée',
            self::CANCELLED => 'Annulée',
        };
    }
}
