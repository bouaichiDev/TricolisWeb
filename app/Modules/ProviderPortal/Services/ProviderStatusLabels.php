<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Statuses\Models\Status;

/**
 * Libellés des statuts (`driver`, `vehicle`…), lus dans le référentiel une
 * fois par source et par requête — pas une fois par ligne d'une liste.
 * Un code absent du référentiel s'affiche tel quel.
 */
final class ProviderStatusLabels
{
    /** @var array<string, array<string, string>> */
    private array $bySource = [];

    public function label(string $source, ?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $this->bySource[$source] ??= Status::where('source', $source)->pluck('label', 'code')->all();

        return $this->bySource[$source][$code] ?? $code;
    }
}
