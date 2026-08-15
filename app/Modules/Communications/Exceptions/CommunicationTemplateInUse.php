<?php

declare(strict_types=1);

namespace App\Modules\Communications\Exceptions;

use RuntimeException;

/**
 * Refus de suppression d'un modèle ou d'une règle encore référencés.
 *
 * Traduit en 409 : la demande est comprise, mais l'état du système l'interdit.
 * Le §42 impose de protéger l'historique — une communication passée doit rester
 * rattachable à ce qui l'a produite.
 */
final class CommunicationTemplateInUse extends RuntimeException
{
    public static function hasRules(): self
    {
        return new self('Ce modèle est utilisé par au moins une règle de communication.');
    }

    public static function hasCommunications(): self
    {
        return new self('Ce modèle a produit des communications : il fait partie de l’historique.');
    }

    public static function ruleHasCommunications(): self
    {
        return new self('Cette règle a produit des communications : elle fait partie de l’historique.');
    }
}
