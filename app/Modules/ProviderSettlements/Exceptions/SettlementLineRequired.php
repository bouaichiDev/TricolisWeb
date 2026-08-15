<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Exceptions;

use RuntimeException;

/**
 * `ProviderSettlement "1" *-- "1..*" ProviderSettlementLine`.
 */
final class SettlementLineRequired extends RuntimeException
{
    public static function lastLine(): self
    {
        return new self('Un décompte doit conserver au moins une ligne : supprimez le décompte pour retirer la dernière.');
    }
}
