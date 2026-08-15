<?php

declare(strict_types=1);

namespace App\Modules\Tours\Exceptions;

use RuntimeException;

/**
 * Conflit métier sur la suppression d'un élément de planification.
 *
 * Traduite en 409 par le contrôleur, jamais en 500 : le refus est une réponse
 * métier, pas une erreur technique.
 */
final class TourResourceStillInUse extends RuntimeException
{
    public static function stopHasPeriods(): self
    {
        return new self('Impossible de supprimer un arrêt encore rattaché à des périodes.');
    }

    public static function serviceHasAssignments(): self
    {
        return new self('Impossible de supprimer un service encore affecté à une période.');
    }

    public static function lastActiveService(): self
    {
        return new self('Un arrêt doit conserver au moins un service actif : supprimez l’arrêt pour retirer le dernier.');
    }

    public static function periodHasAssignments(): self
    {
        return new self('Impossible de supprimer une période qui porte encore des affectations.');
    }
}
