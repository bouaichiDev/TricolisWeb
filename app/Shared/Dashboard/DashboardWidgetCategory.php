<?php

declare(strict_types=1);

namespace App\Shared\Dashboard;

/**
 * Regroupement des widgets dans l'écran de réglage.
 *
 * Distinct de `MenuSection`, qui range des **écrans** : deux widgets d'un même
 * écran peuvent relever de métiers différents — « services prêts à planifier »
 * intéresse le planificateur, « commandes du jour » le bureau, et les deux se
 * lisent dans les commandes. Réutiliser la découpe du menu aurait donc mêlé ce
 * que l'écran de réglage cherche à séparer.
 *
 * Aucune conséquence sur la sécurité : la catégorie range, la permission
 * protège.
 */
enum DashboardWidgetCategory: string
{
    case ADMINISTRATION = 'administration';
    case OPERATIONS = 'operations';
    case PLANNING = 'planning';
    case CLAIMS = 'claims';
    case BILLING = 'billing';
    case STOCK = 'stock';
    case COMMUNICATIONS = 'communications';
    case INTEGRATIONS = 'integrations';
    case QUICK_ACTIONS = 'quick_actions';

    /**
     * Ordre d'affichage, du plus courant au plus rare.
     *
     * Les actions rapides ferment la marche : ce sont des raccourcis, et les
     * placer avant les chiffres qu'ils commentent inverserait la lecture.
     */
    public function position(): int
    {
        return match ($this) {
            self::OPERATIONS => 0,
            self::PLANNING => 1,
            self::CLAIMS => 2,
            self::BILLING => 3,
            self::STOCK => 4,
            self::COMMUNICATIONS => 5,
            self::INTEGRATIONS => 6,
            self::ADMINISTRATION => 7,
            self::QUICK_ACTIONS => 8,
        };
    }
}
