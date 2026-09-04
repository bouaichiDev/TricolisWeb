<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardWidget;
use App\Shared\Dashboard\DashboardWidgetCategory;
use App\Shared\Dashboard\DashboardWidgetSize;
use App\Shared\Dashboard\DashboardWidgetType;

/**
 * Réclamations et preuves de livraison.
 *
 * « Réclamation ouverte » ne se lit **pas** dans `claims.status` : cette
 * colonne est une chaîne libre de 32 caractères, sans énumération ni
 * référentiel semé, et chaque organisme y écrit son propre vocabulaire.
 * Compter les statuts qui ressemblent à « ouvert » aurait donné un chiffre
 * juste chez celui qui a inspiré la liste, et faux chez tous les autres.
 *
 * `closed_at` dit la même chose sans rien supposer : une réclamation non close
 * est ouverte. La colonne est indexée, et le sens ne dépend d'aucun réglage.
 */
final class ClaimsWidgets
{
    /**
     * @return array<int, DashboardWidget>
     */
    public static function all(): array
    {
        return [
            new DashboardWidget(
                key: 'open_claims',
                type: DashboardWidgetType::ALERT,
                category: DashboardWidgetCategory::CLAIMS,
                requiredPermission: 'claims.view',
                defaultPosition: 200,
                route: '/claims',
            ),
            new DashboardWidget(
                key: 'claims_created_today',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::CLAIMS,
                requiredPermission: 'claims.view',
                defaultPosition: 201,
                route: '/claims',
            ),
            new DashboardWidget(
                key: 'recent_claims',
                type: DashboardWidgetType::LIST,
                category: DashboardWidgetCategory::CLAIMS,
                requiredPermission: 'claims.view',
                defaultPosition: 202,
                size: DashboardWidgetSize::MEDIUM,
                route: '/claims',
            ),

            // Les preuves de livraison n'ont pas d'écran de liste : elles se
            // consultent depuis la commande qu'elles prouvent. Le compteur
            // renseigne, il n'ouvre rien.
            new DashboardWidget(
                key: 'pod_created_today',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::CLAIMS,
                requiredPermission: 'proofs_of_delivery.view',
                defaultPosition: 203,
            ),
            new DashboardWidget(
                key: 'services_without_pod',
                type: DashboardWidgetType::ALERT,
                category: DashboardWidgetCategory::CLAIMS,
                requiredPermission: 'proofs_of_delivery.view',
                defaultPosition: 204,
            ),
            // Le meme constat que `services_without_pod`, dans l'autre sens :
            // celui-la compte ce qui manque, celui-ci dit ou l'on en est. Les
            // deux se completent — un chiffre brut ne dit pas s'il est gros,
            // un taux ne dit pas combien de dossiers il represente.
            new DashboardWidget(
                key: 'pod_coverage_rate',
                type: DashboardWidgetType::GAUGE,
                category: DashboardWidgetCategory::CLAIMS,
                requiredPermission: 'proofs_of_delivery.view',
                defaultPosition: 205,
            ),
        ];
    }
}
