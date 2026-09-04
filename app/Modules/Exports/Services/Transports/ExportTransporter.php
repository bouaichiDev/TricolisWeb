<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Transports;

use App\Modules\Exports\Models\CustomerExportConfiguration;

/**
 * Achemine un fichier déjà généré vers la destination d'un client.
 *
 * Un seul rôle : transmettre. La mise en forme appartient aux formateurs — le
 * §85 interdit de regénérer le contenu ici, et un transporteur qui saurait
 * fabriquer du JSON finirait par en fabriquer une seconde version.
 *
 * Une panne se signale par une exception : c'est le distributeur qui décide
 * qu'elle vaut un échec enregistré, et non chaque transporteur dans son coin.
 */
interface ExportTransporter
{
    /**
     * @param  string  $contents  le fichier, déjà mis en forme et encodé
     */
    public function send(
        CustomerExportConfiguration $configuration,
        string $fileName,
        string $contents,
        string $contentType,
    ): void;
}
