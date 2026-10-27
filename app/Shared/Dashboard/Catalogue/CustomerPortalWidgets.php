<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardDrilldown;
use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;
use App\Shared\Dashboard\DashboardWidgetSize;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Widgets du portail client.
 *
 * Même moteur que les widgets internes — mêmes types, mêmes tailles, même
 * configuration par rôle — mais un catalogue à part : ils ne se proposent
 * qu'aux rôles de portée `customer`, ne demandent que des permissions
 * `customer.*`, et leurs routes sont celles du portail.
 *
 * Tous sont activés par défaut : un rôle client que le transporteur n'a pas
 * encore réglé voit un tableau de bord utile, pas une page vide.
 */
final class CustomerPortalWidgets
{
    /**
     * Libellés servis au portail, qui n'a pas de fichier de traduction. Les
     * mêmes figurent dans `frontend/…/fr.json` pour l'écran de réglage interne.
     */
    private const array LABELS = [
        'customer_orders_count' => 'Commandes passées',
        'customer_deliveries_upcoming' => 'Livraisons des 7 prochains jours',
        'customer_orders_by_status' => 'Commandes par statut',
        'customer_recent_orders' => 'Dernières commandes',
        'customer_new_order' => 'Nouvelle commande',
    ];

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? $key;
    }

    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            new DashboardWidget(
                key: 'customer_orders_count',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::CUSTOMER_PORTAL,
                requiredPermission: 'customer.orders.view',
                defaultPosition: 10,
                defaultEnabled: true,
                route: '/orders',
                periodAware: true,
            ),
            new DashboardWidget(
                key: 'customer_deliveries_upcoming',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::CUSTOMER_PORTAL,
                requiredPermission: 'customer.orders.view',
                defaultPosition: 20,
                defaultEnabled: true,
                route: '/orders',
            ),
            new DashboardWidget(
                key: 'customer_orders_by_status',
                type: DashboardWidgetType::DONUT,
                category: DashboardWidgetCategory::CUSTOMER_PORTAL,
                requiredPermission: 'customer.orders.view',
                defaultPosition: 30,
                size: DashboardWidgetSize::MEDIUM,
                defaultEnabled: true,
                route: '/orders',
                periodAware: true,
                drilldown: new DashboardDrilldown(seriesParam: 'status', fromParam: 'dateFrom', toParam: 'dateTo'),
            ),
            new DashboardWidget(
                key: 'customer_recent_orders',
                type: DashboardWidgetType::LIST,
                category: DashboardWidgetCategory::CUSTOMER_PORTAL,
                requiredPermission: 'customer.orders.view',
                defaultPosition: 40,
                size: DashboardWidgetSize::MEDIUM,
                defaultEnabled: true,
                route: '/orders',
                periodAware: true,
            ),
            new DashboardWidget(
                key: 'customer_new_order',
                type: DashboardWidgetType::QUICK_ACTION,
                category: DashboardWidgetCategory::QUICK_ACTIONS,
                requiredPermission: 'customer.orders.create',
                defaultPosition: 800,
                defaultEnabled: true,
                route: '/orders/create',
            ),
        ];
    }
}
