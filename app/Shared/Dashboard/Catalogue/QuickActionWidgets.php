<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Raccourcis vers ce qu'un métier fait dix fois par jour.
 *
 * Chaque action est **un widget**, et non une entrée d'une carte unique
 * « Actions rapides ». Les deux formes se valaient à l'écran ; celle-ci se
 * règle. Un rôle qui ne crée jamais de facture décoche `new_invoice` sans
 * toucher au reste, alors qu'une carte unique aurait demandé un second niveau
 * de configuration à l'intérieur d'un widget — pour le même résultat.
 *
 * La permission n'est pas décorative : elle est celle de **l'action**, pas de
 * la lecture. `new_order` demande `orders.create`, et un rôle qui ne peut que
 * consulter les commandes ne se voit pas proposer d'en créer une.
 *
 * Deux actions ouvrent une liste plutôt qu'un formulaire — les réclamations et
 * les règles de communication se créent dans une boîte de dialogue posée sur
 * leur liste. Y mener est exact ; inventer `/claims/create` aurait donné une
 * page introuvable.
 */
final class QuickActionWidgets
{
    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            new DashboardWidget(
                key: 'new_order',
                type: DashboardWidgetType::QUICK_ACTION,
                category: DashboardWidgetCategory::QUICK_ACTIONS,
                requiredPermission: 'orders.create',
                defaultPosition: 800,
                route: '/orders/create',
            ),
            new DashboardWidget(
                key: 'new_invoice',
                type: DashboardWidgetType::QUICK_ACTION,
                category: DashboardWidgetCategory::QUICK_ACTIONS,
                requiredPermission: 'invoices.create',
                defaultPosition: 801,
                route: '/billing/invoices/create',
            ),
            new DashboardWidget(
                key: 'open_planning',
                type: DashboardWidgetType::QUICK_ACTION,
                category: DashboardWidgetCategory::QUICK_ACTIONS,
                requiredPermission: 'tours.view',
                defaultPosition: 802,
                route: '/planning',
            ),
            new DashboardWidget(
                key: 'new_stock_movement',
                type: DashboardWidgetType::QUICK_ACTION,
                category: DashboardWidgetCategory::QUICK_ACTIONS,
                requiredPermission: 'stock_movements.create',
                defaultPosition: 803,
                route: '/stock/movements/create',
            ),
            new DashboardWidget(
                key: 'new_claim',
                type: DashboardWidgetType::QUICK_ACTION,
                category: DashboardWidgetCategory::QUICK_ACTIONS,
                requiredPermission: 'claims.create',
                defaultPosition: 804,
                route: '/claims',
            ),
            new DashboardWidget(
                key: 'new_communication_rule',
                type: DashboardWidgetType::QUICK_ACTION,
                category: DashboardWidgetCategory::QUICK_ACTIONS,
                requiredPermission: 'communication_rules.create',
                defaultPosition: 805,
                route: '/communications/rules',
            ),
        ];
    }
}
