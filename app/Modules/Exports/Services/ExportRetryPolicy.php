<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services;

use App\Modules\Exports\Models\CustomerExportConfiguration;

/**
 * La relance automatique d'un export, telle que la configuration la demande.
 *
 * `settings.retry = {"maxAttempts": 3, "backoffSeconds": 300}`. **Par défaut,
 * une seule tentative** : c'est le comportement historique du moteur (un
 * échec reste en `failed`, repris à la main). Une destination qui le demande
 * — un REST Push qu'une panne passagère ne doit pas perdre — l'active.
 *
 * Seule une erreur passagère (`TransientExportFailure`) déclenche une
 * relance ; une erreur 4xx ou de configuration ne se corrige pas en
 * réessayant.
 */
final readonly class ExportRetryPolicy
{
    public const int MAX_ATTEMPTS = 5;

    public function maxAttempts(CustomerExportConfiguration $configuration): int
    {
        $declared = $this->retry($configuration)['maxAttempts'] ?? 1;

        return max(1, min(self::MAX_ATTEMPTS, is_int($declared) ? $declared : 1));
    }

    /** Secondes avant la tentative suivante, doublées à chaque échec. */
    public function delayAfter(CustomerExportConfiguration $configuration, int $attempt): int
    {
        $base = $this->retry($configuration)['backoffSeconds'] ?? 60;
        $base = is_int($base) ? max(10, min(3600, $base)) : 60;

        return min(6 * 3600, $base * (2 ** max(0, $attempt - 1)));
    }

    /** @return array<string, mixed> */
    private function retry(CustomerExportConfiguration $configuration): array
    {
        $retry = ($configuration->settings ?? [])['retry'] ?? null;

        return is_array($retry) ? $retry : [];
    }
}
