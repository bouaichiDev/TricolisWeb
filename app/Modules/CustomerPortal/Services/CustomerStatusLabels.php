<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\Statuses\Services\StatusMachine;
use BackedEnum;

/**
 * Libellé et icône d'un statut, tels que le référentiel les définit.
 *
 * Le libellé vient de la table `statuses` — celle que l'administrateur
 * renomme — et non de l'énumération, qui n'en est que la graine. Le
 * `StatusMachine` mémorise le référentiel : une liste de vingt-cinq
 * commandes ne fait qu'une requête.
 */
final class CustomerStatusLabels
{
    public function __construct(private readonly StatusMachine $statuses) {}

    /**
     * @return array{code: string|null, label: string|null, icon: string|null, color: string|null}
     */
    public function present(string $source, BackedEnum|string|null $status): array
    {
        $code = $status instanceof BackedEnum ? (string) $status->value : $status;
        $row = $this->statuses->status($source, $code);
        $fallback = $status instanceof BackedEnum && method_exists($status, 'label') ? $status->label() : $code;

        return ['code' => $code, 'label' => $row?->label ?? $fallback, 'icon' => $row?->icon, 'color' => $row?->color];
    }
}
