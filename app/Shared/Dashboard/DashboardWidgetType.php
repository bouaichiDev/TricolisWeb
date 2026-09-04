<?php

declare(strict_types=1);

namespace App\Shared\Dashboard;

/**
 * Forme d'un widget, et rien d'autre.
 *
 * C'est le seul vocabulaire que le frontend accepte : `DashboardWidgetRenderer`
 * fait correspondre ces neuf valeurs à neuf composants, et refuse tout le reste.
 * Un nom de composant React voyageant depuis la base — ou pire, choisi par un
 * administrateur — permettrait d'afficher n'importe quoi ; un jeu fermé de
 * types ne le permet pas.
 *
 * Le type gouverne aussi la **forme de la donnée** renvoyée par le résolveur :
 * une valeur pour un KPI, des séries pour un graphe, des lignes pour une liste.
 * `DashboardWidgetData` en tient la promesse côté frontend.
 *
 * Trois formes se ressemblent et ne se remplacent pas — c'est le nombre de
 * catégories et la nature de la question qui tranchent :
 *
 * - `CHART` — une **barre de composition** : beaucoup de parts, aux noms longs,
 *   qu'on veut aussi lire au chiffre près. Les statuts d'une commande, qui sont
 *   dix.
 * - `DONUT` — un **camembert** : peu de parts — six au plus — dont on veut la
 *   proportion d'un coup d'œil. Au-delà, les secteurs voisins deviennent
 *   indistinguables et le camembert ne dit plus rien qu'une barre ne dirait
 *   mieux.
 * - `GAUGE` — **un seul rapport** : une part contre son tout. Un camembert à
 *   deux secteurs répond à la même question en occupant deux fois la place, et
 *   fait passer le reste pour une catégorie alors qu'il n'est qu'un reste.
 *
 * Deux autres portent le **temps**, ce qu'aucune des précédentes ne fait — elles
 * photographient l'instant. Leur donnée est la même, et le type dit comment la
 * lire :
 *
 * - `COLUMNS` — **des colonnes empilées, un jour par colonne**. On y lit un
 *   volume quotidien et sa composition. C'est la forme du « combien, et de
 *   quoi » jour après jour.
 * - `LINES` — **des courbes**. On y lit une tendance, pas un volume : l'oeil
 *   suit une pente bien mieux qu'il ne compare des hauteurs de barres
 *   voisines. Deux ou trois séries au plus, et **toujours sur un seul axe** —
 *   deux échelles verticales sur un même graphe inventent une corrélation que
 *   les données ne portent pas.
 */
enum DashboardWidgetType: string
{
    case KPI = 'kpi';
    case CHART = 'chart';
    case DONUT = 'donut';
    case GAUGE = 'gauge';
    case COLUMNS = 'columns';
    case LINES = 'lines';
    case LIST = 'list';
    case ALERT = 'alert';
    case QUICK_ACTION = 'quick_action';
}
