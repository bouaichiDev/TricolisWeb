<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;
use App\Shared\Dashboard\DashboardWidgetSize;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Commandes et services : ce que le bureau regarde le matin.
 *
 * Les widgets de service n'ont **pas de route**, et cette absence est un choix.
 * `/services` est le catalogue des prestations vendues, pas la liste des
 * services d'une commande : une carte « services prêts à planifier » qui y
 * mènerait tromperait deux fois. Les services se lisent dans leur commande, ou
 * dans le planning.
 */
final class OperationsWidgets
{
    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            new DashboardWidget(
                key: 'orders_today',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 0,
                route: '/orders',
            ),
            new DashboardWidget(
                key: 'orders_to_plan',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 1,
                route: '/orders',
            ),
            new DashboardWidget(
                key: 'orders_in_progress',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 2,
                route: '/orders',
            ),
            new DashboardWidget(
                key: 'orders_completed_today',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 3,
                route: '/orders',
            ),
            new DashboardWidget(
                key: 'services_ready_to_plan',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'order_services.view',
                defaultPosition: 4,
            ),
            new DashboardWidget(
                key: 'services_in_progress',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'order_services.view',
                defaultPosition: 5,
            ),

            // Un service échoué n'est pas un chiffre parmi d'autres : il
            // demande une reprise. D'où le type ALERT, qui se teinte quand le
            // compte n'est pas nul, et reste sobre quand il l'est.
            new DashboardWidget(
                key: 'services_failed',
                type: DashboardWidgetType::ALERT,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'order_services.view',
                defaultPosition: 6,
            ),

            new DashboardWidget(
                key: 'recent_orders',
                type: DashboardWidgetType::LIST,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 7,
                size: DashboardWidgetSize::MEDIUM,
                route: '/orders',
            ),
            new DashboardWidget(
                key: 'orders_by_status',
                type: DashboardWidgetType::CHART,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 8,
                size: DashboardWidgetSize::MEDIUM,
                route: '/orders',
            ),
        ];
    }
}
