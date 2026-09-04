<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;
use App\Shared\Dashboard\DashboardWidgetSize;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Facturation et décomptes fournisseurs.
 *
 * Un seul montant figure ici, et il n'est **pas** un KPI : le total des
 * factures closes sur la période est un `CHART`, une barre par devise. Les
 * factures portent `currency_code`, et un organisme qui facture en CHF, en EUR
 * et en MAD verrait, avec une valeur unique, la somme de trois monnaies — un
 * nombre qui ne veut rien dire et que personne ne repère, puisqu'il a l'air
 * d'un chiffre d'affaires.
 *
 * Le reste compte des lignes, ce qui se somme sans risque.
 */
final class BillingWidgets
{
    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            // La préfacturation est gouvernée par `price_lists.view` : c'est la
            // permission que l'écran `/billing/prebilling` exige déjà, et en
            // choisir une autre ici aurait proposé une carte menant à un refus.
            new DashboardWidget(
                key: 'prebilling_services',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::BILLING,
                requiredPermission: 'price_lists.view',
                defaultPosition: 300,
                route: '/billing/prebilling',
            ),

            new DashboardWidget(
                key: 'draft_invoices',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::BILLING,
                requiredPermission: 'invoices.view',
                defaultPosition: 301,
                route: '/billing/invoices',
            ),
            new DashboardWidget(
                key: 'closed_invoices_today',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::BILLING,
                requiredPermission: 'invoices.view',
                defaultPosition: 302,
                route: '/billing/invoices',
            ),
            new DashboardWidget(
                key: 'closed_invoices_period_total',
                type: DashboardWidgetType::CHART,
                category: DashboardWidgetCategory::BILLING,
                requiredPermission: 'invoices.view',
                defaultPosition: 303,
                size: DashboardWidgetSize::MEDIUM,
                route: '/billing/invoices',
            ),
            new DashboardWidget(
                key: 'invoices_by_status',
                type: DashboardWidgetType::CHART,
                category: DashboardWidgetCategory::BILLING,
                requiredPermission: 'invoices.view',
                defaultPosition: 304,
                size: DashboardWidgetSize::MEDIUM,
                route: '/billing/invoices',
            ),
            new DashboardWidget(
                key: 'recent_invoices',
                type: DashboardWidgetType::LIST,
                category: DashboardWidgetCategory::BILLING,
                requiredPermission: 'invoices.view',
                defaultPosition: 305,
                size: DashboardWidgetSize::MEDIUM,
                route: '/billing/invoices',
            ),

            new DashboardWidget(
                key: 'draft_provider_settlements',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::BILLING,
                requiredPermission: 'provider_settlements.view',
                defaultPosition: 306,
                route: '/billing/settlements',
            ),
            new DashboardWidget(
                key: 'recent_provider_settlements',
                type: DashboardWidgetType::LIST,
                category: DashboardWidgetCategory::BILLING,
                requiredPermission: 'provider_settlements.view',
                defaultPosition: 307,
                size: DashboardWidgetSize::MEDIUM,
                route: '/billing/settlements',
            ),
        ];
    }
}
