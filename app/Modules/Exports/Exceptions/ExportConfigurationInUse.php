<?php

declare(strict_types=1);

namespace App\Modules\Exports\Exceptions;

use RuntimeException;

/**
 * Conflit métier sur la suppression d'une configuration d'export.
 */
final class ExportConfigurationInUse extends RuntimeException
{
    public static function hasJobs(): self
    {
        return new self('Impossible de supprimer une configuration qui a déjà produit des exports.');
    }
}
