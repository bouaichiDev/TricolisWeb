<?php

declare(strict_types=1);

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/**
 * `Invoice "1" *-- "1..*" InvoiceLine` : une facture sans ligne n'existe pas.
 *
 * Traduite en 409 par le contrôleur, jamais en 500.
 */
final class InvoiceLineRequired extends RuntimeException
{
    public static function lastLine(): self
    {
        return new self('Une facture doit conserver au moins une ligne : supprimez la facture pour retirer la dernière.');
    }
}
