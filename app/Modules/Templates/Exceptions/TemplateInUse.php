<?php

declare(strict_types=1);

namespace App\Modules\Templates\Exceptions;

use RuntimeException;

/**
 * Refus de suppression d'un modèle encore référencé.
 *
 * Traduit en 409 : la demande est comprise, mais l'état du système l'interdit.
 * Le §28 impose de protéger l'historique — une communication passée, une
 * facture close doivent rester rattachables à ce qui les a produites.
 */
final class TemplateInUse extends RuntimeException
{
    public static function hasRules(): self
    {
        return new self('Ce modèle est utilisé par au moins une règle de communication.');
    }

    public static function hasCommunications(): self
    {
        return new self('Ce modèle a produit des communications : il fait partie de l’historique.');
    }

    public static function hasInvoices(): self
    {
        return new self('Ce modèle a produit des factures : il fait partie de l’historique.');
    }
}
