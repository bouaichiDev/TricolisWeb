<?php

declare(strict_types=1);

namespace App\Shared\Dashboard;

/**
 * Forme d'un widget, et rien d'autre.
 *
 * C'est le seul vocabulaire que le frontend accepte : `DashboardWidgetRenderer`
 * fait correspondre ces cinq valeurs à cinq composants, et refuse tout le reste.
 * Un nom de composant React voyageant depuis la base — ou pire, choisi par un
 * administrateur — permettrait d'afficher n'importe quoi ; un jeu fermé de
 * types ne le permet pas.
 *
 * Le type gouverne aussi la **forme de la donnée** renvoyée par le résolveur :
 * une valeur pour un KPI, des séries pour un graphe, des lignes pour une liste.
 * `DashboardWidgetData` en tient la promesse côté frontend.
 */
enum DashboardWidgetType: string
{
    case KPI = 'kpi';
    case CHART = 'chart';
    case LIST = 'list';
    case ALERT = 'alert';
    case QUICK_ACTION = 'quick_action';
}
