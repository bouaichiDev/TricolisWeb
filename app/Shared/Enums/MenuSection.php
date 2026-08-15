<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Section de menu à laquelle une permission se rattache.
 *
 * `Permission.module` est une découpe **technique** : 48 valeurs, dont
 * `tour_stop_services` et `provider_settlement_lines`. Grouper le formulaire de
 * rôle dessus produisait 48 sections, dans lesquelles personne ne pouvait
 * composer un rôle. La section est la découpe **métier** correspondante, et il
 * y en a une dizaine.
 *
 * Elle est portée par la permission et non par le module, parce que les deux ne
 * coïncident pas toujours : `organizations.view` sert à consulter **son**
 * organisation, quand `organizations.create` relève de l'administration de la
 * plateforme. Même module, deux sections.
 */
enum MenuSection: string
{
    case DASHBOARD = 'dashboard';
    case CUSTOMERS = 'customers';
    case RESOURCES = 'resources';
    case OPERATIONS = 'operations';
    case STOCK = 'stock';
    case BILLING = 'billing';
    case COMMUNICATIONS = 'communications';
    case INTEGRATIONS = 'integrations';
    case ADMINISTRATION = 'administration';
    case PLATFORM = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::DASHBOARD => 'Tableau de bord',
            self::CUSTOMERS => 'Clients',
            self::RESOURCES => 'Ressources',
            self::OPERATIONS => 'Exploitation',
            self::STOCK => 'Stock',
            self::BILLING => 'Facturation',
            self::COMMUNICATIONS => 'Communications',
            self::INTEGRATIONS => 'Intégrations',
            self::ADMINISTRATION => 'Administration',
            self::PLATFORM => 'Plateforme',
        };
    }

    /**
     * Ordre d'affichage, du plus courant au plus rare.
     *
     * L'ordre alphabétique placerait « Administration » en tête, devant
     * « Clients » — ce que personne ne cherche en premier.
     */
    public function position(): int
    {
        return match ($this) {
            self::DASHBOARD => 0,
            self::CUSTOMERS => 1,
            self::RESOURCES => 2,
            self::OPERATIONS => 3,
            self::STOCK => 4,
            self::BILLING => 5,
            self::COMMUNICATIONS => 6,
            self::INTEGRATIONS => 7,
            self::ADMINISTRATION => 8,
            self::PLATFORM => 9,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
