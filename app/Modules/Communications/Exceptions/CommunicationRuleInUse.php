<?php

declare(strict_types=1);

namespace App\Modules\Communications\Exceptions;

use RuntimeException;

/**
 * Refus de suppression d'une règle ayant déjà produit des communications.
 *
 * Traduit en 409. Le §145 privilégie la désactivation : une règle inactive
 * cesse de produire des messages sans effacer ceux qu'elle a produits, et
 * l'historique conserve l'explication de leur origine.
 */
final class CommunicationRuleInUse extends RuntimeException
{
    public static function hasCommunications(): self
    {
        return new self('Cette règle a produit des communications : elle fait partie de l’historique. Désactivez-la plutôt.');
    }
}
