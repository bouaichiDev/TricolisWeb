<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Transports;

use App\Modules\Exports\Models\CustomerExportConfiguration;

/**
 * Ne transmet rien : le fichier attend qu'on vienne le chercher.
 *
 * **C'est un transport à part entière**, et non l'absence de transport. Le
 * fichier est bien produit et rangé — l'historique porte son nom, son
 * empreinte et sa date — mais aucun système distant n'est appelé.
 *
 * Le cas est courant : un client qui relève ses factures lui-même, ou une
 * intégration en cours de mise en place chez lui. Sans ce mode, il faudrait
 * soit inventer une destination factice, soit se passer d'export et perdre la
 * trace de ce qui a été généré.
 *
 * L'envoi est marqué transmis, parce qu'il l'est : le fichier est disponible,
 * et c'est tout ce que cette destination promet.
 */
final readonly class ManualExportTransporter implements ExportTransporter
{
    public function send(
        CustomerExportConfiguration $configuration,
        string $fileName,
        string $contents,
        string $contentType,
    ): void {
        // Rien a faire : le fichier est deja ecrit par le repartiteur, et c'est
        // exactement ce que ce transport promet.
    }
}
