<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Exceptions;

use RuntimeException;

/**
 * Conflit métier : le type de véhicule est encore utilisé.
 */
final class VehicleTypeStillInUse extends RuntimeException
{
    public static function hasVehicles(): self
    {
        return new self('Impossible de supprimer un type de véhicule utilisé par des véhicules.');
    }
}
