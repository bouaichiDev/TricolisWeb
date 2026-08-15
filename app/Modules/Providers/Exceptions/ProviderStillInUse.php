<?php

declare(strict_types=1);

namespace App\Modules\Providers\Exceptions;

use RuntimeException;

/**
 * Conflit métier : le fournisseur porte encore des ressources.
 *
 * Traduite en 409 par le contrôleur, jamais en 500 : le refus est une réponse
 * métier, pas une erreur technique.
 */
final class ProviderStillInUse extends RuntimeException
{
    public static function hasDriversOrVehicles(): self
    {
        return new self('Impossible de supprimer un fournisseur qui possède encore des chauffeurs ou des véhicules.');
    }
}
