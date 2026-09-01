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

            // Fournisseurs et leurs ressources — Phase 4. Pas d'entree pour les
            // types de vehicule : le referentiel a rejoint `/types`.
            new MenuEntry(
                code: 'providers',
                labelKey: 'nav.providers',
                icon: 'Truck',
                section: MenuSection::RESOURCES,
                position: 23,
                route: '/providers',
                permission: 'providers.view',
                parent: 'resources',
            ),
            new MenuEntry(
                code: 'drivers',
                labelKey: 'nav.drivers',
                icon: 'IdCard',
                section: MenuSection::RESOURCES,
                position: 24,
                route: '/drivers',
                permission: 'drivers.view',
                parent: 'resources',
            ),
            new MenuEntry(
                code: 'vehicles',
                labelKey: 'nav.vehicles',
                icon: 'Truck',
                section: MenuSection::RESOURCES,
                position: 25,
                route: '/vehicles',
                permission: 'vehicles.view',
                parent: 'resources',
            ),

            // Exploitation — Phase 2. Les référentiels de colis sont
            // gouvernés par `packages.*` : `PermissionSeeder` ne leur donne
            // aucune permission propre.
            // Transport — Phase 5. La planification n'est ni un referentiel ni
            // une commande : c'est ce qu'on fait rouler.
            new MenuEntry(
                code: 'transport',
                labelKey: 'nav.transport',
                icon: 'Route',
                section: MenuSection::TRANSPORT,
                position: 40,
            ),
            new MenuEntry(
                code: 'planning',
                labelKey: 'nav.planning',
                icon: 'CalendarRange',
                section: MenuSection::TRANSPORT,
                position: 41,
                route: '/planning',
                permission: 'tours.view',
                parent: 'transport',
            ),
            new MenuEntry(
                code: 'tours',
                labelKey: 'nav.tours',
                icon: 'Route',
                section: MenuSection::TRANSPORT,
                position: 42,
                route: '/tours',
                permission: 'tours.view',
                parent: 'transport',
            ),

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
                code: 'claims',
                labelKey: 'nav.claims',
                icon: 'MessageSquareWarning',
                section: MenuSection::OPERATIONS,
                position: 35,
                route: '/claims',
                permission: 'claims.view',
                parent: 'operations',
            ),
            // Une seule entree pour tous les referentiels de type : celui
            // qu'un organisme ajoute y apparait sans qu'on touche au catalogue.
            new MenuEntry(
                code: 'types',
                labelKey: 'nav.types',
                icon: 'Tags',
                section: MenuSection::OPERATIONS,
                position: 33,
                route: '/types',
                permission: 'types.view',
                parent: 'operations',
            ),

            // Stock client chez le transporteur — Phase 7. Le stock est
            // physiquement celui du transporteur mais reste separe par client :
            // les cinq entrees suivent les cinq entites du modele, et rien
            // d'autre. Ni receptions, ni inventaires, ni ajustements, ni zones,
            // ni entrepots, ni lots : ces tables n'existent pas.
            new MenuEntry(
                code: 'stock',
                labelKey: 'nav.stock',
                icon: 'Warehouse',
                section: MenuSection::STOCK,
                position: 40,
            ),
            // La vue d'ensemble lit les soldes : c'est bien `stock_balances.view`
            // qu'elle demande, pas une permission propre au tableau de bord.
            new MenuEntry(
                code: 'stock-overview',
                labelKey: 'nav.stockOverview',
                icon: 'LayoutDashboard',
                section: MenuSection::STOCK,
                position: 41,
                route: '/stock',
                permission: 'stock_balances.view',
                parent: 'stock',
            ),
            new MenuEntry(
                code: 'stock-items',
                labelKey: 'nav.stockItems',
                icon: 'Boxes',
                section: MenuSection::STOCK,
                position: 42,
                route: '/stock/items',
                permission: 'stock_items.view',
                parent: 'stock',
            ),
            new MenuEntry(
                code: 'stock-locations',
                labelKey: 'nav.stockLocations',
                icon: 'Warehouse',
                section: MenuSection::STOCK,
                position: 43,
                route: '/stock/locations',
                permission: 'stock_locations.view',
                parent: 'stock',
            ),
            new MenuEntry(
                code: 'stock-movements',
                labelKey: 'nav.stockMovements',
                icon: 'ArrowRightLeft',
                section: MenuSection::STOCK,
                position: 44,
                route: '/stock/movements',
                permission: 'stock_movements.view',
                parent: 'stock',
            ),
            new MenuEntry(
                code: 'stock-reservations',
                labelKey: 'nav.stockReservations',
                icon: 'ClipboardList',
                section: MenuSection::STOCK,
                position: 45,
                route: '/stock/reservations',
                permission: 'stock_reservations.view',
                parent: 'stock',
            ),

            // Facturation — Phase 6. Les factures clients et les décomptes
            // fournisseurs sont deux comptes opposés du même transport : ce
            // qu'on encaisse, ce qu'on reverse. Les envois les suivent, parce
            // qu'une facture clôturée part chez le client.
            new MenuEntry(
                code: 'billing',
                labelKey: 'nav.billing',
                icon: 'ReceiptText',
                section: MenuSection::BILLING,
                position: 50,
            ),
            new MenuEntry(
                code: 'prebilling',
                labelKey: 'nav.prebilling',
                icon: 'Calculator',
                section: MenuSection::BILLING,
                position: 51,
                route: '/billing/prebilling',
                permission: 'price_lists.view',
                parent: 'billing',
            ),
            new MenuEntry(
                code: 'invoices',
                labelKey: 'nav.invoices',
                icon: 'ReceiptText',
                section: MenuSection::BILLING,
                position: 52,
                route: '/billing/invoices',
                permission: 'invoices.view',
                parent: 'billing',
            ),
            new MenuEntry(
                code: 'pricing-global',
                labelKey: 'nav.pricingGlobal',
                icon: 'Tags',
                section: MenuSection::BILLING,
                position: 53,
                route: '/billing/pricing/global',
                permission: 'price_lists.view',
                parent: 'billing',
            ),
            new MenuEntry(
                code: 'pricing-customers',
                labelKey: 'nav.pricingCustomers',
                icon: 'Tags',
                section: MenuSection::BILLING,
                position: 54,
                route: '/billing/pricing/customers',
                permission: 'price_lists.view',
                parent: 'billing',
            ),
            new MenuEntry(
                code: 'formula-tester',
                labelKey: 'nav.formulaTester',
                icon: 'Calculator',
                section: MenuSection::BILLING,
                position: 55,
                route: '/billing/pricing/tester',
                permission: 'price_lists.view',
                parent: 'billing',
            ),
            new MenuEntry(
                code: 'provider-settlements',
                labelKey: 'nav.settlements',
                icon: 'HandCoins',
                section: MenuSection::BILLING,
                position: 56,
                route: '/billing/settlements',
                permission: 'provider_settlements.view',
                parent: 'billing',
            ),
            new MenuEntry(
                code: 'export-configurations',
                labelKey: 'nav.exportConfigurations',
                icon: 'Send',
                section: MenuSection::BILLING,
                position: 57,
                route: '/billing/export-configurations',
                permission: 'customer_export_configurations.view',
                parent: 'billing',
            ),
            new MenuEntry(
                code: 'export-jobs',
                labelKey: 'nav.exports',
                icon: 'FileOutput',
                section: MenuSection::BILLING,
                position: 58,
                route: '/billing/exports',
                permission: 'export_jobs.view',
                parent: 'billing',
            ),

            // Configuration des messages — Phase 9.
            //
            // `templates` et `billing-templates` menent au **meme ecran** : le
            // §0.15 interdit deux CRUD pour une seule table. Seul le filtre
            // d'arrivee differe, parce qu'un comptable et un exploitant ne
            // cherchent pas le meme modele en ouvrant la page.
            new MenuEntry(
                code: 'templates',
                labelKey: 'nav.templates',
                icon: 'Mail',
                section: MenuSection::COMMUNICATIONS,
                position: 60,
                route: '/templates?category=communication',
                permission: 'templates.view',
            ),

            new MenuEntry(
                code: 'communication-rules',
                labelKey: 'nav.communicationRules',
                icon: 'Workflow',
                section: MenuSection::COMMUNICATIONS,
                position: 61,
                route: '/communications/rules',
                permission: 'communication_rules.view',
            ),

            new MenuEntry(
                code: 'communication-history',
                labelKey: 'nav.communicationHistory',
                icon: 'Send',
                section: MenuSection::COMMUNICATIONS,
                position: 62,
                route: '/communications/history',
                permission: 'order_communications.view',
            ),

            new MenuEntry(
                code: 'billing-templates',
                labelKey: 'nav.invoiceTemplates',
                icon: 'FileText',
                section: MenuSection::BILLING,
                position: 59,
                route: '/templates?templateType=invoice',
                permission: 'templates.view',
                parent: 'billing',
            ),

            new MenuEntry(
                code: 'journey',
                labelKey: 'nav.journey',
                icon: 'Route',
                section: MenuSection::OPERATIONS,
                position: 36,
                route: '/journey',
                permission: 'tracking_event_definitions.view',
                parent: 'operations',
            ),

            new MenuEntry(
                code: 'api-configurations',
                labelKey: 'nav.apiConfigurations',
                icon: 'Plug',
                section: MenuSection::INTEGRATIONS,
                position: 70,
                route: '/api-configurations',
                permission: 'api_configurations.view',
            ),

            // Integrations clients — Phase 8. Quatre entrees, pour les quatre
            // entites du modele. Ni webhooks, ni exports planifies, ni journaux
            // d'appels API, ni historique d'import : ces tables n'existent pas,
            // et le §67 interdit d'en annoncer les ecrans.
            //
            // A ne pas confondre avec `api-configurations` ci-dessus, qui est
            // le sens inverse : les API que l'organisme appelle, et non les
            // cles avec lesquelles ses clients l'appellent (§19).
            new MenuEntry(
                code: 'integrations',
                labelKey: 'nav.integrations',
                icon: 'Plug',
                section: MenuSection::INTEGRATIONS,
                position: 71,
            ),
            new MenuEntry(
                code: 'integration-imports',
                labelKey: 'nav.importConfigurations',
                icon: 'FileInput',
                section: MenuSection::INTEGRATIONS,
                position: 72,
                route: '/integrations/imports',
                permission: 'customer_import_configurations.view',
                parent: 'integrations',
            ),
            new MenuEntry(
                code: 'integration-api-access',
                labelKey: 'nav.customerApiAccess',
                icon: 'KeyRound',
                section: MenuSection::INTEGRATIONS,
                position: 73,
                route: '/integrations/api-access',
                permission: 'customer_api_configurations.view',
                parent: 'integrations',
            ),
            new MenuEntry(
                code: 'integration-exports',
                labelKey: 'nav.exportConfigurationsDirectory',
                icon: 'Send',
                section: MenuSection::INTEGRATIONS,
                position: 74,
                route: '/integrations/exports',
                permission: 'customer_export_configurations.view',
                parent: 'integrations',
            ),
            new MenuEntry(
                code: 'integration-export-jobs',
                labelKey: 'nav.exportHistory',
                icon: 'History',
                section: MenuSection::INTEGRATIONS,
                position: 75,
                route: '/integrations/export-jobs',
                permission: 'export_jobs.view',
                parent: 'integrations',
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
            // Le catalogue des variables tarifaires : il decide de ce qu'un
            // organisme peut ecrire dans une formule, et sa source est un
            // chemin vers la base. C'est donc une affaire de plateforme.
            new MenuEntry(
                code: 'pricing-variables',
                labelKey: 'nav.pricingVariables',
                icon: 'Variable',
                section: MenuSection::PLATFORM,
                position: 20,
                route: '/pricing-variables',
                permission: 'pricing_variables.manage',
                scope: RoleScope::PLATFORM,
            ),

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
