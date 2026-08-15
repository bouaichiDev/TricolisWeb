<?php

declare(strict_types=1);

namespace App\Modules\Claims\Exceptions;

use RuntimeException;

/**
 * Conflit métier sur la suppression d'une réclamation.
 *
 * Traduite en 409 par le contrôleur, jamais en 500 : le refus est une réponse
 * métier, pas une erreur technique.
 */
final class ClaimNotDeletable extends RuntimeException
{
    public static function alreadyClosed(): self
    {
        return new self('Une réclamation clôturée ne peut pas être supprimée.');
    }
}
