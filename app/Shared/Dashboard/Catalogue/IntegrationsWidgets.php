<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;
use App\Shared\Dashboard\DashboardWidgetSize;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Échanges avec l'extérieur : envois de fichiers et accès API.
 *
 * Ces widgets **comptent**, ils ne montrent pas. Une configuration d'export
 * porte un hôte, un identifiant, un mot de passe chiffré et un chemin de dépôt ;
 * une configuration d'API porte des identifiants chiffrés. Rien de tout cela
 * n'a de raison de traverser le tableau de bord, et la liste des envois récents
 * ne remonte donc que le nom du fichier et son état — jamais `storage_path`,
 * jamais un secret.
 */
final class IntegrationsWidgets
{
    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            new DashboardWidget(
                key: 'export_jobs_failed',
                type: DashboardWidgetType::ALERT,
                category: DashboardWidgetCategory::INTEGRATIONS,
                requiredPermission: 'export_jobs.view',
                defaultPosition: 600,
                route: '/integrations/export-jobs',
            ),
            new DashboardWidget(
                key: 'export_jobs_pending',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::INTEGRATIONS,
                requiredPermission: 'export_jobs.view',
                defaultPosition: 601,
                route: '/integrations/export-jobs',
            ),
            new DashboardWidget(
                key: 'exports_sent_today',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::INTEGRATIONS,
                requiredPermission: 'export_jobs.view',
                defaultPosition: 602,
                route: '/integrations/export-jobs',
            ),
            new DashboardWidget(
                key: 'recent_export_jobs',
                type: DashboardWidgetType::LIST,
                category: DashboardWidgetCategory::INTEGRATIONS,
                requiredPermission: 'export_jobs.view',
                defaultPosition: 603,
                size: DashboardWidgetSize::MEDIUM,
                route: '/integrations/export-jobs',
            ),
            new DashboardWidget(
                key: 'active_api_configurations',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::INTEGRATIONS,
                requiredPermission: 'api_configurations.view',
                defaultPosition: 604,
                route: '/api-configurations',
            ),
            new DashboardWidget(
                key: 'active_export_configurations',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::INTEGRATIONS,
                requiredPermission: 'customer_export_configurations.view',
                defaultPosition: 605,
                route: '/billing/export-configurations',
            ),
            // Partis contre tentes, sur la journee. Le compteur d'echecs dit
            // combien il y en a ; celui-ci dit si c'est beaucoup — trois echecs
            // sur cinq envois et trois sur trois cents ne demandent pas la meme
            // reaction.
            new DashboardWidget(
                key: 'export_success_rate',
                type: DashboardWidgetType::GAUGE,
                category: DashboardWidgetCategory::INTEGRATIONS,
                requiredPermission: 'export_jobs.view',
                defaultPosition: 606,
                route: '/integrations/export-jobs',
            ),
        ];
    }
}
