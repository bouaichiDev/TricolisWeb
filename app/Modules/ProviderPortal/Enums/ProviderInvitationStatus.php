<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Enums;

enum ProviderInvitationStatus: string
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
