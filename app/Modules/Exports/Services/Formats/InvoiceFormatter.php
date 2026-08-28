<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Formats;

use App\Modules\Exports\DTOs\InvoiceExportData;

/**
 * Rend une facture dans un format de fichier.
 *
 * Un seul rôle : mettre en forme. Le transport est l'affaire d'une autre
 * famille de classes — le §85 interdit de regénérer le JSON dans chaque
 * transporteur.
 */
interface InvoiceFormatter
{
    /**
     * @param  array<string, mixed>  $settings  mapping déclaratif du client
     */
    public function render(InvoiceExportData $invoice, array $settings, string $encoding): string;

    /** Extension du fichier produit, sans le point. */
    public function extension(): string;

    /** Type de contenu, pour un transport qui en demande un. */
    public function contentType(): string;
}
