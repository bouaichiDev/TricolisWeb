<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Shared\Enums\MenuSection;

/**
 * Section de menu de chaque permission.
 *
 * Deux niveaux, et le second existe pour une raison précise : un module tombe
 * en général tout entier dans une section, mais pas toujours.
 * `organizations.view` sert à consulter **son** organisation — Administration ;
 * `organizations.create` administre la plateforme. Même module, deux sections.
 * Les exceptions sont donc déclarées par code, et l'emportent sur le module.
 */
final class PermissionMenuMap
{
    /**
     * Section par module. Couvre les 48 modules du référentiel.
     *
     * @var array<string, MenuSection>
     */
    private const array BY_MODULE = [
        'dashboard' => MenuSection::DASHBOARD,

        // Le client et tout ce qui le décrit : ses lieux, ses interlocuteurs,
        // ses pièces, son catalogue.
        'customers' => MenuSection::CUSTOMERS,
        'customer_sites' => MenuSection::CUSTOMERS,
        'addresses' => MenuSection::CUSTOMERS,
        'contacts' => MenuSection::CUSTOMERS,
        'documents' => MenuSection::CUSTOMERS,
        'catalogs' => MenuSection::CUSTOMERS,

        // Ce que le transporteur possède pour exécuter.
        'agencies' => MenuSection::RESOURCES,
        'depots' => MenuSection::RESOURCES,
        'vehicles' => MenuSection::RESOURCES,
        'vehicle_types' => MenuSection::RESOURCES,
        'drivers' => MenuSection::RESOURCES,
        'providers' => MenuSection::RESOURCES,

        // De la commande à la preuve de livraison.
        'orders' => MenuSection::OPERATIONS,
        'order_lines' => MenuSection::OPERATIONS,
        'order_services' => MenuSection::OPERATIONS,
        'services' => MenuSection::OPERATIONS,
        'packages' => MenuSection::OPERATIONS,
        'tours' => MenuSection::OPERATIONS,
        'tour_stops' => MenuSection::OPERATIONS,
        'tour_stop_services' => MenuSection::OPERATIONS,
        'tour_periods' => MenuSection::OPERATIONS,
        'tour_period_assignments' => MenuSection::OPERATIONS,
        'tracking_events' => MenuSection::OPERATIONS,
        'proofs_of_delivery' => MenuSection::OPERATIONS,
        'claims' => MenuSection::OPERATIONS,

        'tracking_event_definitions' => MenuSection::OPERATIONS,
        'api_configurations' => MenuSection::INTEGRATIONS,

        'stock_items' => MenuSection::STOCK,
        'stock_balances' => MenuSection::STOCK,
        'stock_locations' => MenuSection::STOCK,
        'stock_movements' => MenuSection::STOCK,
        'stock_reservations' => MenuSection::STOCK,

        'invoices' => MenuSection::BILLING,
        'invoice_lines' => MenuSection::BILLING,
        'provider_settlements' => MenuSection::BILLING,
        'provider_settlement_lines' => MenuSection::BILLING,

        'communication_rules' => MenuSection::COMMUNICATIONS,
        'communication_templates' => MenuSection::COMMUNICATIONS,
        'communication_attachments' => MenuSection::COMMUNICATIONS,
        'order_communications' => MenuSection::COMMUNICATIONS,

        // Échanges avec les systèmes du client.
        'customer_api_configurations' => MenuSection::INTEGRATIONS,
        'customer_import_configurations' => MenuSection::INTEGRATIONS,
        'customer_export_configurations' => MenuSection::INTEGRATIONS,
        'export_jobs' => MenuSection::INTEGRATIONS,

        'users' => MenuSection::ADMINISTRATION,
        'roles' => MenuSection::ADMINISTRATION,
        'audit' => MenuSection::ADMINISTRATION,
        'subscriptions' => MenuSection::ADMINISTRATION,
        'organizations' => MenuSection::ADMINISTRATION,
        'statuses' => MenuSection::PLATFORM,
    ];

    /**
     * Permissions dont la section diffère de celle de leur module.
     *
     * Créer ou supprimer une organisation dépasse le périmètre d'un organisme :
     * ce sont les deux permissions réservées à la plateforme, et les isoler
     * dans leur propre section évite de les présenter à côté de « Modifier mon
     * organisation », dont elles n'ont ni la portée ni les conséquences.
     *
     * @var array<string, MenuSection>
     */
    private const array BY_CODE = [
        'organizations.create' => MenuSection::PLATFORM,
        'organizations.delete' => MenuSection::PLATFORM,
    ];

    public static function sectionFor(string $code, string $module): MenuSection
    {
        return self::BY_CODE[$code] ?? self::BY_MODULE[$module] ?? MenuSection::ADMINISTRATION;
    }

    /**
     * Modules connus de la table. Un module absent retomberait silencieusement
     * dans « Administration » : un test s'assure qu'aucun n'y échoue.
     *
     * @return array<int, string>
     */
    public static function knownModules(): array
    {
        return array_keys(self::BY_MODULE);
    }
}
