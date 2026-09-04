<?php

declare(strict_types=1);

namespace App\Shared\Dashboard;

/**
 * Place qu'un widget occupe dans la grille.
 *
 * Elle vient du **catalogue**, pas de la configuration : un compteur tient dans
 * une tuile, un graphe demande une demi-largeur, et laisser régler cela
 * reviendrait à écrire un éditeur de page — alors que le besoin est de choisir
 * ce qu'un métier voit, et dans quel ordre.
 */
enum DashboardWidgetSize: string
{
    case SMALL = 'small';
    case MEDIUM = 'medium';
    case LARGE = 'large';
    case FULL = 'full';
}
