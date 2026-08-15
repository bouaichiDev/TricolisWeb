<?php

declare(strict_types=1);

namespace App\Shared\Menu;

use App\Shared\Enums\MenuSection;
use App\Shared\Enums\RoleScope;

/**
 * Catalogue des entrées de menu livrées avec l'application.
 *
 * C'est la **source unique** : le frontend n'écrit plus sa propre liste, il
 * consomme celle-ci. Un test vérifie que chaque `route` existe dans le routeur
 * React et que chaque `permission` existe dans le référentiel — les deux
 * défauts qui rendent une entrée inaccessible sans qu'aucune erreur ne soit
 * levée.
 *
 * Le catalogue ne contient que des entrées **réellement atteignables**. Y
 * inscrire les modules des phases suivantes conduirait à proposer des écrans
 * qui n'existent pas.
 */
final class MenuCatalogue
{
    /**
     * @return array<int, MenuEntry>
     */
    public static function entries(): array
    {
        return [
            ...self::organizationEntries(),
            ...self::platformEntries(),
        ];
    }

    /**
     * @return array<int, MenuEntry>
     */
    private static function organizationEntries(): array
    {
        return [
            new MenuEntry(
                code: 'dashboard',
                labelKey: 'nav.dashboard',
                icon: 'LayoutDashboard',
                section: MenuSection::DASHBOARD,
                position: 0,
                route: '/dashboard',
                permission: 'dashboard.view',
            ),
            new MenuEntry(
                code: 'customers',
                labelKey: 'nav.customers',
                icon: 'Building2',
                section: MenuSection::CUSTOMERS,
                position: 10,
                route: '/customers',
                permission: 'customers.view',
            ),

            new MenuEntry(
                code: 'resources',
                labelKey: 'nav.resources',
                icon: 'Boxes',
                section: MenuSection::RESOURCES,
                position: 20,
            ),
            new MenuEntry(
                code: 'agencies',
                labelKey: 'nav.agencies',
                icon: 'Network',
                section: MenuSection::RESOURCES,
                position: 21,
                route: '/agencies',
                permission: 'agencies.view',
                parent: 'resources',
            ),
            new MenuEntry(
                code: 'depots',
                labelKey: 'nav.depots',
                icon: 'Warehouse',
                section: MenuSection::RESOURCES,
                position: 22,
                route: '/depots',
                permission: 'depots.view',
                parent: 'resources',
            ),

            // L'administration ne se masque pas : un organisme qui la
            // retirerait n'aurait plus d'écran pour revenir en arrière.
            new MenuEntry(
                code: 'administration',
                labelKey: 'nav.administration',
                icon: 'Settings',
                section: MenuSection::ADMINISTRATION,
                position: 80,
                alwaysVisible: true,
            ),
            new MenuEntry(
                code: 'my-organization',
                labelKey: 'nav.myOrganization',
                icon: 'Building2',
                section: MenuSection::ADMINISTRATION,
                position: 81,
                route: '/my-organization',
                permission: 'organizations.view',
                parent: 'administration',
                alwaysVisible: true,
            ),
            new MenuEntry(
                code: 'users',
                labelKey: 'nav.users',
                icon: 'Users',
                section: MenuSection::ADMINISTRATION,
                position: 82,
                route: '/users',
                permission: 'users.view',
                parent: 'administration',
            ),
            new MenuEntry(
                code: 'roles',
                labelKey: 'nav.roles',
                icon: 'Shield',
                section: MenuSection::ADMINISTRATION,
                position: 83,
                route: '/roles',
                permission: 'roles.view',
                parent: 'administration',
            ),
            new MenuEntry(
                code: 'audit',
                labelKey: 'nav.audit',
                icon: 'ClipboardList',
                section: MenuSection::ADMINISTRATION,
                position: 84,
                route: '/audit',
                permission: 'audit.view',
                parent: 'administration',
            ),
        ];
    }

    /**
     * Menu de la plateforme : une seule entrée, et c'est voulu.
     *
     * Utilisateurs, rôles et journal d'audit sont portés par une organisation ;
     * les proposer ici obligerait à en désigner une.
     *
     * @return array<int, MenuEntry>
     */
    private static function platformEntries(): array
    {
        return [
            new MenuEntry(
                code: 'organizations',
                labelKey: 'nav.organizations',
                icon: 'Building2',
                section: MenuSection::PLATFORM,
                position: 0,
                route: '/organizations',
                permission: 'organizations.view',
                scope: RoleScope::PLATFORM,
                alwaysVisible: true,
            ),
        ];
    }

    /**
     * @return array<int, MenuEntry>
     */
    public static function forScope(RoleScope $scope): array
    {
        return array_values(array_filter(
            self::entries(),
            static fn (MenuEntry $entry): bool => $entry->scope === $scope,
        ));
    }

    public static function find(string $code): ?MenuEntry
    {
        foreach (self::entries() as $entry) {
            if ($entry->code === $code) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_map(static fn (MenuEntry $entry): string => $entry->code, self::entries());
    }
}
