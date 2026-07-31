<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum ContactRole: string
{
    case LOAD = 'load';
    case DELIVERY = 'delivery';
    case BILLING = 'billing';
    case OPERATIONS = 'operations';
    case EMERGENCY = 'emergency';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LOAD => 'Chargement',
            self::DELIVERY => 'Livraison',
            self::BILLING => 'Facturation',
            self::OPERATIONS => 'Opérations',
            self::EMERGENCY => 'Urgence',
            self::OTHER => 'Autre',
        };
    }
}
