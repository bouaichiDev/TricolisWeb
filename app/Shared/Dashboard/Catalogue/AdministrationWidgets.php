<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Les quatre cartes que le tableau de bord affichait en dur, et trois autres.
 *
 * Ce sont les seules à naître `defaultEnabled`. Le choix n'est pas esthétique :
 * un rôle qui n'a rien configuré doit retrouver exactement ce qu'il voyait
 * avant, sans qu'on ait à écrire une ligne de configuration pour chacun. Les
 * quarante autres widgets attendent d'être demandés — les activer d'office
 * aurait rempli le tableau de bord de chiffres que personne n'a réclamés.
 *
 * L'intersection avec les permissions s'applique aux défauts comme au reste :
 * un rôle sans `customers.view` ne reçoit pas le compteur de clients, même s'il
 * est activé par défaut.
 */
final class AdministrationWidgets
{
    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            new DashboardWidget(
                key: 'customers_count',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::ADMINISTRATION,
                requiredPermission: 'customers.view',
                defaultPosition: 700,
                defaultEnabled: true,
                route: '/customers',
            ),
            new DashboardWidget(
                key: 'agencies_count',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::ADMINISTRATION,
                requiredPermission: 'agencies.view',
                defaultPosition: 701,
                defaultEnabled: true,
                route: '/agencies',
            ),
            new DashboardWidget(
                key: 'users_count',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::ADMINISTRATION,
                requiredPermission: 'users.view',
                defaultPosition: 702,
                defaultEnabled: true,
                route: '/users',
            ),
            new DashboardWidget(
                key: 'roles_count',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::ADMINISTRATION,
                requiredPermission: 'roles.view',
                defaultPosition: 703,
                defaultEnabled: true,
                route: '/roles',
            ),
            new DashboardWidget(
                key: 'providers_count',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::ADMINISTRATION,
                requiredPermission: 'providers.view',
                defaultPosition: 704,
                route: '/providers',
            ),
            new DashboardWidget(
                key: 'drivers_count',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::ADMINISTRATION,
                requiredPermission: 'drivers.view',
                defaultPosition: 705,
                route: '/drivers',
            ),
            new DashboardWidget(
                key: 'vehicles_count',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::ADMINISTRATION,
                requiredPermission: 'vehicles.view',
                defaultPosition: 706,
                route: '/vehicles',
            ),
        ];
    }
}
