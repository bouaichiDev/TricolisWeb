<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum OrganizationStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::ACTIVE => 'Actif',
            self::SUSPENDED => 'Suspendu',
            self::CLOSED => 'Clôturé',
        };
    }

    public function allowsLogin(): bool
    {
        return $this === self::ACTIVE;
    }
}
