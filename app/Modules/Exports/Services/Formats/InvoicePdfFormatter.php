<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Formats;

use App\Modules\Exports\DTOs\InvoiceExportData;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * La facture en PDF, telle qu'un humain la lit.
 *
 * **Le mapping de champs ne s'y applique pas, et c'est voulu.** Renommer
 * `invoiceNumber` en `numero` sert un automate qui cherche une clé ; un PDF n'a
 * pas de clés, il a des libellés en français devant des valeurs. Ce que le §66
 * autorise de configurer — les valeurs fixes — trouve en revanche sa place en
 * pied de page, là où les clients demandent leur code fournisseur ou une
 * mention légale.
 *
 * **L'encodage déclaré est ignoré** : un PDF embarque ses polices et sa propre
 * table de caractères. Prétendre le produire en ISO-8859-1 serait mentir sur le
 * fichier livré.
 *
 * La mise en page vit dans une vue Blade, pas ici : une facture se retouche
 * (logo, mentions, ordre des colonnes) bien plus souvent que la logique qui la
 * produit, et mélanger les deux rendrait chaque retouche risquée.
 */
final readonly class InvoicePdfFormatter implements InvoiceFormatter
{
    private const string VIEW = 'exports.invoice';

    private const string PAPER = 'a4';

    public function render(InvoiceExportData $invoice, array $settings, string $encoding): string
    {
        $statics = is_array($settings['staticValues'] ?? null) ? $settings['staticValues'] : [];

        return Pdf::loadView(self::VIEW, [
            'invoice' => $invoice,
            'title' => $this->title($settings),
            'footnotes' => $this->footnotes($statics),
        ])->setPaper(self::PAPER)->output();
    }

    public function extension(): string
    {
        return 'pdf';
    }

    public function contentType(): string
    {
        return 'application/pdf';
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function title(array $settings): string
    {
        $declared = $settings['documentTitle'] ?? null;

        return is_string($declared) && trim($declared) !== '' ? trim($declared) : 'Facture';
    }

    /**
     * Les valeurs fixes du client, rendues en pied de document.
     *
     * Seuls les scalaires passent : un tableau imbriqué n'a pas de place dans
     * une ligne de bas de page, et l'y aplatir donnerait une bouillie.
     *
     * @param  array<string, mixed>  $statics
     * @return array<string, string>
     */
    private function footnotes(array $statics): array
    {
        $notes = [];

        foreach ($statics as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $notes[$key] = (string) $value;
            }
        }

        return $notes;
    }
}
