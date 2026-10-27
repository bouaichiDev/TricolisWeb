<?php

declare(strict_types=1);

namespace App\Modules\Exports\Exceptions;

use RuntimeException;

/**
 * Conflit métier sur la relance d'un export.
 */
final class ExportJobNotRetryable extends RuntimeException
{
    public static function alreadySent(): self
    {
        return new self('Cet export a déjà été transmis : le relancer produirait un doublon chez le client.');
    }

    public static function inactiveConfiguration(): self
    {
        return new self('La configuration de cet export est désactivée : réactivez-la avant de relancer.');
    }
}
