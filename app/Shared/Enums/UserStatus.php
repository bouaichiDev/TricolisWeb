<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum UserStatus: string
{
    case INVITED = 'invited';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case DISABLED = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::INVITED => 'Invité',
            self::ACTIVE => 'Actif',
            self::SUSPENDED => 'Suspendu',
            self::DISABLED => 'Désactivé',
        };
    }

    public function allowsLogin(): bool
    {
        return $this === self::ACTIVE;
    }
}
