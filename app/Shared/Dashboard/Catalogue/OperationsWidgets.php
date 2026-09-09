<?php

declare(strict_types=1);

namespace App\Shared\Dashboard\Catalogue;

use App\Shared\Dashboard\DashboardDrilldown;
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
                periodAware: true,
            ),
            new DashboardWidget(
                key: 'orders_to_plan',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 1,
                route: '/orders',
                periodAware: true,
            ),
            new DashboardWidget(
                key: 'orders_in_progress',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 2,
                route: '/orders',
                periodAware: true,
            ),
            new DashboardWidget(
                key: 'orders_completed_today',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 3,
                route: '/orders',
                periodAware: true,
            ),
            new DashboardWidget(
                key: 'services_ready_to_plan',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'order_services.view',
                defaultPosition: 4,
                periodAware: true,
            ),
            new DashboardWidget(
                key: 'services_in_progress',
                type: DashboardWidgetType::KPI,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'order_services.view',
                defaultPosition: 5,
                periodAware: true,
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
                periodAware: true,
            ),

            new DashboardWidget(
                key: 'recent_orders',
                type: DashboardWidgetType::LIST,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 7,
                size: DashboardWidgetSize::MEDIUM,
                route: '/orders',
                periodAware: true,
            ),
            new DashboardWidget(
                key: 'orders_by_status',
                type: DashboardWidgetType::CHART,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 8,
                size: DashboardWidgetSize::MEDIUM,
                route: '/orders',
                periodAware: true,
                // Cliquer une part ouvre les commandes **de ce statut**. Sans
                // ce nom de paramètre, la carte menait à la liste entière :
                // celui qui vise « Confirmée » sait ce qu'il veut voir, et la
                // refaire au filtre à la main était le geste que la carte
                // promettait d'épargner.
                drilldown: new DashboardDrilldown(
                    seriesParam: 'status',
                    // Les bornes servent ici a **reporter la periode** de
                    // l'ecran. Une repartition lue sur aout qui ouvrirait les
                    // commandes de tous les temps montrerait autre chose que la
                    // part qu'on vient de cliquer.
                    fromParam: 'createdFrom',
                    toParam: 'createdTo',
                ),
            ),
            // Camembert : la provenance se lit en proportion, et un organisme
            // n'en emploie que deux ou trois sur les neuf possibles. Les
            // statuts, eux, restent en barre — ils sont dix, et leurs noms
            // sont longs.
            new DashboardWidget(
                key: 'orders_by_source',
                type: DashboardWidgetType::DONUT,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 9,
                size: DashboardWidgetSize::MEDIUM,
                route: '/orders',
                periodAware: true,
                drilldown: new DashboardDrilldown(
                    seriesParam: 'source',
                    fromParam: 'createdFrom',
                    toParam: 'createdTo',
                ),
            ),

            // Le temps, que rien d'autre ici ne montre : les autres widgets
            // photographient l'instant. Quatorze colonnes tiennent sur une
            // demi-largeur ; trente en auraient fait des traits.
            new DashboardWidget(
                key: 'orders_per_day',
                type: DashboardWidgetType::COLUMNS,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 10,
                size: DashboardWidgetSize::LARGE,
                route: '/orders',
                periodAware: true,
                // Une colonne désigne **un jour**, et la liste sait le filtrer
                // par les deux bornes de sa fenêtre : le même jour des deux
                // côtés. Sa légende désigne en plus un statut, et les deux se
                // combinent — c'est exactement ce que la colonne montre.
                drilldown: new DashboardDrilldown(
                    seriesParam: 'status',
                    fromParam: 'createdFrom',
                    toParam: 'createdTo',
                ),
            ),

            // Une tendance, pas un volume : l'oeil suit une pente bien mieux
            // qu'il ne compare des hauteurs de colonnes voisines. D'ou trente
            // jours ici, et quatorze au-dessus.
            new DashboardWidget(
                key: 'orders_trend',
                type: DashboardWidgetType::LINES,
                category: DashboardWidgetCategory::OPERATIONS,
                requiredPermission: 'orders.view',
                defaultPosition: 11,
                size: DashboardWidgetSize::LARGE,
                route: '/orders',
                periodAware: true,
                // Pas de `seriesParam` ici, et c'est voulu : « créées » et
                // « achevées » ne sont pas des valeurs de colonne, ce sont deux
                // façons de compter. Les envoyer comme statut aurait donné une
                // liste vide sur la première, et fausse sur la seconde.
                drilldown: new DashboardDrilldown(
                    fromParam: 'createdFrom',
                    toParam: 'createdTo',
                ),
            ),
        ];
    }
}
