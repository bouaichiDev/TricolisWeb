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

            // Les catalogues n'ont pas de route globale : l'entree ouvre la
            // liste des clients, d'ou on atteint le catalogue de chacun.
            // Pointer vers /catalogs appellerait une API qui n'existe pas.

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

            // Exploitation — Phase 2. Les référentiels de colis sont
            // gouvernés par `packages.*` : `PermissionSeeder` ne leur donne
            // aucune permission propre.
            new MenuEntry(
                code: 'operations',
                labelKey: 'nav.operations',
                icon: 'ClipboardList',
                section: MenuSection::OPERATIONS,
                position: 30,
            ),
            new MenuEntry(
                code: 'orders',
                labelKey: 'nav.orders',
                icon: 'ClipboardList',
                section: MenuSection::OPERATIONS,
                position: 31,
                route: '/orders',
                permission: 'orders.view',
                parent: 'operations',
            ),
            new MenuEntry(
                code: 'services',
                labelKey: 'nav.services',
                icon: 'Wrench',
                section: MenuSection::OPERATIONS,
                position: 32,
                route: '/services',
                permission: 'services.view',
                parent: 'operations',
            ),
            new MenuEntry(
                code: 'package-types',
                labelKey: 'nav.packageTypes',
                icon: 'Package',
                section: MenuSection::OPERATIONS,
                position: 33,
                route: '/package-types',
                permission: 'packages.view',
                parent: 'operations',
            ),
            new MenuEntry(
                code: 'grouping-types',
                labelKey: 'nav.groupingTypes',
                icon: 'Layers',
                section: MenuSection::OPERATIONS,
                position: 34,
                route: '/package-grouping-types',
                permission: 'packages.view',
                parent: 'operations',
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
            // Referentiel des statuts : commun a toute la plateforme, donc
            // gere depuis le perimetre plateforme et non par organisation.
            new MenuEntry(
                code: 'statuses',
                labelKey: 'nav.statuses',
                icon: 'Tags',
                section: MenuSection::PLATFORM,
                position: 1,
                route: '/statuses',
                permission: 'statuses.view',
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
