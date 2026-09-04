<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;
use App\Shared\Dashboard\DashboardWidgetSize;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Communications envoyées aux clients.
 *
 * Les statuts viennent de `CommunicationStatus`, l'énumération réelle — neuf
 * valeurs, dont `scheduled`, `queued` et `failed`. « En attente » y recouvre
 * deux états distincts que le domaine sépare : programmée, et mise en file. Le
 * compteur les additionne, parce que celui qui regarde son tableau de bord veut
 * savoir combien partent bientôt, pas où elles en sont dans la mécanique.
 */
final class CommunicationsWidgets
{
    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            new DashboardWidget(
                key: 'communications_scheduled',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::COMMUNICATIONS,
                requiredPermission: 'order_communications.view',
                defaultPosition: 500,
                route: '/communications/history',
            ),
            new DashboardWidget(
                key: 'communications_failed',
                type: DashboardWidgetType::ALERT,
                category: DashboardWidgetCategory::COMMUNICATIONS,
                requiredPermission: 'order_communications.view',
                defaultPosition: 501,
                route: '/communications/history',
            ),
            new DashboardWidget(
                key: 'communications_sent_today',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::COMMUNICATIONS,
                requiredPermission: 'order_communications.view',
                defaultPosition: 502,
                route: '/communications/history',
            ),
            new DashboardWidget(
                key: 'recent_communications',
                type: DashboardWidgetType::LIST,
                category: DashboardWidgetCategory::COMMUNICATIONS,
                requiredPermission: 'order_communications.view',
                defaultPosition: 503,
                size: DashboardWidgetSize::MEDIUM,
                route: '/communications/history',
            ),
            // Cinq canaux, pas un de plus : l'enumeration les fixe, et cinq
            // parts se lisent d'un coup d'oeil.
            new DashboardWidget(
                key: 'communications_by_channel',
                type: DashboardWidgetType::DONUT,
                category: DashboardWidgetCategory::COMMUNICATIONS,
                requiredPermission: 'order_communications.view',
                defaultPosition: 504,
                size: DashboardWidgetSize::MEDIUM,
                route: '/communications/history',
            ),

            // Le volume quotidien, par canal. `created_at` et non `sent_at` :
            // une communication programmee pour la semaine prochaine a bien ete
            // produite aujourd'hui.
            new DashboardWidget(
                key: 'communications_per_day',
                type: DashboardWidgetType::COLUMNS,
                category: DashboardWidgetCategory::COMMUNICATIONS,
                requiredPermission: 'order_communications.view',
                defaultPosition: 505,
                size: DashboardWidgetSize::LARGE,
                route: '/communications/history',
            ),
        ];
    }
}
