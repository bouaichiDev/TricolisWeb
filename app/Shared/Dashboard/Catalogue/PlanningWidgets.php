<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;
use App\Shared\Dashboard\DashboardWidgetSize;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Tournées et planification.
 *
 * Une absence volontaire : **la disponibilité des chauffeurs et des
 * véhicules**. Aucune table ne la porte, et la déduire des tournées du jour
 * donnerait un chiffre faux dès qu'un congé n'y figure pas. Un tableau de bord
 * qui affiche un mauvais chiffre est pire qu'un tableau de bord qui n'en
 * affiche aucun : le premier se croit.
 *
 * `services_without_gps` se calcule en revanche vraiment : l'adresse d'un
 * service porte `latitude` et `longitude`, toutes deux nullables, et un service
 * sans coordonnées ne peut pas être placé sur une tournée optimisée. C'est une
 * alerte, pas un compteur.
 */
final class PlanningWidgets
{
    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            new DashboardWidget(
                key: 'tours_today',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::PLANNING,
                requiredPermission: 'tours.view',
                defaultPosition: 100,
                route: '/tours',
            ),
            new DashboardWidget(
                key: 'draft_tours',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::PLANNING,
                requiredPermission: 'tours.view',
                defaultPosition: 101,
                route: '/tours',
            ),
            new DashboardWidget(
                key: 'planned_tours',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::PLANNING,
                requiredPermission: 'tours.view',
                defaultPosition: 102,
                route: '/tours',
            ),
            new DashboardWidget(
                key: 'tours_in_progress',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::PLANNING,
                requiredPermission: 'tours.view',
                defaultPosition: 103,
                route: '/tours',
            ),
            new DashboardWidget(
                key: 'completed_tours_today',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::PLANNING,
                requiredPermission: 'tours.view',
                defaultPosition: 104,
                route: '/tours',
            ),

            // Le planning est l'écran où l'on répare les deux suivants : c'est
            // là qu'un service en attente trouve une tournée, et là qu'une
            // adresse sans coordonnées se voit.
            new DashboardWidget(
                key: 'unplanned_services',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::PLANNING,
                requiredPermission: 'tours.view',
                defaultPosition: 105,
                route: '/planning',
            ),
            new DashboardWidget(
                key: 'services_without_gps',
                type: DashboardWidgetType::ALERT,
                category: DashboardWidgetCategory::PLANNING,
                requiredPermission: 'tours.view',
                defaultPosition: 106,
                route: '/planning',
            ),

            new DashboardWidget(
                key: 'recent_tours',
                type: DashboardWidgetType::LIST,
                category: DashboardWidgetCategory::PLANNING,
                requiredPermission: 'tours.view',
                defaultPosition: 107,
                size: DashboardWidgetSize::MEDIUM,
                route: '/tours',
            ),
            // Camembert et non barre de composition : une tournee a six statuts,
            // pas dix. C'est la borne au-dela de laquelle deux secteurs voisins
            // deviennent indistinguables — les commandes, elles, restent en
            // barre pour cette raison.
            new DashboardWidget(
                key: 'tours_by_status',
                type: DashboardWidgetType::DONUT,
                category: DashboardWidgetCategory::PLANNING,
                requiredPermission: 'tours.view',
                defaultPosition: 108,
                size: DashboardWidgetSize::MEDIUM,
                route: '/tours',
            ),

            // Un seul rapport : ce qui est place, sur ce qui reste a placer.
            // Un camembert a deux secteurs repondrait a la meme question en
            // occupant deux fois la place, et ferait passer le reste pour une
            // categorie alors qu'il n'est qu'un reste.
            new DashboardWidget(
                key: 'planning_coverage_rate',
                type: DashboardWidgetType::GAUGE,
                category: DashboardWidgetCategory::PLANNING,
                requiredPermission: 'tours.view',
                defaultPosition: 109,
                route: '/planning',
            ),
        ];
    }
}
