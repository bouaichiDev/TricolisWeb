<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Exports\Models\CustomerExportConfiguration;

/**
 * Le nom du fichier livré au client.
 *
 * **Un gabarit, sur une liste blanche.** Le §81 veut le champ existant
 * `fileNamePattern`, avec des variables connues — jamais une expression. Un
 * gabarit contenant `../` ou un appel quelconque ne produit rien de plus qu'un
 * nom aplati : les séparateurs sont retirés après substitution, pas avant, sans
 * quoi une variable pourrait en réintroduire.
 *
 * Sans gabarit valable, le §129 autorise un repli : le numéro de facture et
 * l'extension suffisent à identifier le fichier.
 */
final readonly class ExportFileName
{
    public function build(
        CustomerExportConfiguration $configuration,
        Invoice $invoice,
        string $extension,
    ): string {
        $pattern = trim((string) $configuration->file_name_pattern);

        $name = $pattern === ''
            ? $this->fallback($invoice, $extension)
            : $this->fromPattern($pattern, $invoice, $extension);

        $clean = $this->sanitise($name);

        return $clean === '' ? $this->fallback($invoice, $extension) : $clean;
    }

    private function fromPattern(string $pattern, Invoice $invoice, string $extension): string
    {
        $replaced = strtr($pattern, [
            '{invoiceNumber}' => (string) $invoice->invoice_number,
            '{invoiceDate}' => $invoice->invoice_date?->format('Ymd') ?? '',
            '{currencyCode}' => (string) $invoice->currency_code,
            '{format}' => $extension,
        ]);

        // Une variable inconnue vaut vide plutot qu'un accolade dans le nom :
        // le client recevrait sinon `invoice_{clientId}.xml`.
        $replaced = preg_replace('/\{[A-Za-z0-9_.]*\}/', '', $replaced) ?? $replaced;

        return str_ends_with(strtolower($replaced), '.'.$extension)
            ? $replaced
            : $replaced.'.'.$extension;
    }

    private function fallback(Invoice $invoice, string $extension): string
    {
        return sprintf('%s.%s', $this->sanitise((string) $invoice->invoice_number), $extension);
    }

    /**
     * Un nom de fichier plat, sans chemin ni caractère surprenant.
     *
     * Le nettoyage vient **après** la substitution : une valeur métier peut
     * contenir une barre oblique, et la retirer avant ne protégerait de rien.
     */
    private function sanitise(string $name): string
    {
        $flat = str_replace(['\\', '/'], '_', $name);
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $flat) ?? '';

        return trim(preg_replace('/_+/', '_', $clean) ?? '', '._-');
    }
}
