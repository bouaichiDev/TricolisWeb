<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;
use App\Shared\Dashboard\DashboardWidgetSize;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Stock.
 *
 * **Pas de « stock bas ».** Aucune colonne ne porte de seuil : ni l'article, ni
 * l'emplacement, ni le solde. Le calculer demanderait d'en inventer un — dix
 * unités ? un mois de consommation ? — et l'alerte qui en sortirait dirait
 * quelque chose sur la valeur choisie, pas sur le stock. Le jour où un seuil
 * existera, le widget s'ajoutera ici sans rien changer d'autre.
 *
 * Les quantités sont des décimales, et le domaine les stocke en `decimal(12,3)`.
 * Elles remontent telles quelles, sans arrondi : c'est le frontend qui décide
 * comment les écrire.
 */
final class StockWidgets
{
    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            new DashboardWidget(
                key: 'stock_items_count',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::STOCK,
                requiredPermission: 'stock_items.view',
                defaultPosition: 400,
                route: '/stock/items',
            ),
            new DashboardWidget(
                key: 'stock_total_quantity',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::STOCK,
                requiredPermission: 'stock_balances.view',
                defaultPosition: 401,
                route: '/stock/balances',
            ),
            new DashboardWidget(
                key: 'stock_reserved_quantity',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::STOCK,
                requiredPermission: 'stock_balances.view',
                defaultPosition: 402,
                route: '/stock/balances',
            ),
            new DashboardWidget(
                key: 'stock_available_quantity',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::STOCK,
                requiredPermission: 'stock_balances.view',
                defaultPosition: 403,
                route: '/stock/balances',
            ),
            new DashboardWidget(
                key: 'active_stock_reservations',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::STOCK,
                requiredPermission: 'stock_reservations.view',
                defaultPosition: 404,
                route: '/stock/reservations',
            ),
            new DashboardWidget(
                key: 'recent_stock_movements',
                type: DashboardWidgetType::LIST,
                category: DashboardWidgetCategory::STOCK,
                requiredPermission: 'stock_movements.view',
                defaultPosition: 405,
                size: DashboardWidgetSize::MEDIUM,
                route: '/stock/movements',
            ),
        ];
    }
}
