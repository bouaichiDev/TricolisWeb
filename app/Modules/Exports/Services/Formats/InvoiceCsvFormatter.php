<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Formats;

use App\Modules\Exports\DTOs\InvoiceExportData;
use App\Modules\Exports\Services\ExportFieldMapping;

/**
 * La facture en CSV.
 *
 * **Un tableau, donc une ligne par prestation** — et l'en-tête de facture
 * répété sur chacune. Un CSV n'a pas de hiérarchie : livrer le corps et le
 * détail dans deux fichiers obligerait le destinataire à les recoller, ce que
 * personne ne fait de bon cœur.
 *
 * Le séparateur suit la convention du client (`delimiter`, `enclosure` dans
 * `settings`) : le point-virgule est la norme dans les tableurs francophones,
 * la virgule ailleurs, et livrer le mauvais rend le fichier illisible sans
 * qu'aucune erreur ne le dise.
 *
 * `fputcsv` échappe lui-même guillemets et sauts de ligne — une raison sociale
 * contenant une virgule ne décale pas la colonne suivante. L'échappement par
 * antislash est explicitement désactivé : PHP 8.4 le déprécie, et il produisait
 * des fichiers que les tableurs relisaient de travers.
 */
final readonly class InvoiceCsvFormatter implements InvoiceFormatter
{
    private const string DEFAULT_DELIMITER = ';';

    public function __construct(private ExportFieldMapping $mapping) {}

    public function render(InvoiceExportData $invoice, array $settings, string $encoding): string
    {
        $payload = $this->mapping->apply($invoice, $settings);
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        unset($payload['lines']);

        $header = array_map(static fn ($value): string => (string) $value, $payload);

        // Les cles de la premiere ligne fixent les colonnes : le mapping du
        // client a pu les renommer, et l'en-tete doit suivre.
        $lineColumns = $lines === [] ? [] : array_keys($this->flatten($lines[0]));

        $handle = fopen('php://temp', 'r+');
        $delimiter = $this->character($settings, 'delimiter', self::DEFAULT_DELIMITER);
        $enclosure = $this->character($settings, 'enclosure', '"');

        fputcsv($handle, array_merge(array_keys($header), $lineColumns), $delimiter, $enclosure, '');

        foreach ($lines === [] ? [[]] : $lines as $line) {
            fputcsv(
                $handle,
                array_values(array_merge($header, $this->flatten(is_array($line) ? $line : []))),
                $delimiter,
                $enclosure,
                '',
            );
        }

        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function contentType(): string
    {
        return 'text/csv';
    }

    /**
     * Aplatit l'adresse dans la ligne : un CSV n'a qu'un niveau.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, string>
     */
    private function flatten(array $line): array
    {
        $flat = [];

        foreach ($line as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $nested => $inner) {
                    $flat[$key.'.'.$nested] = (string) $inner;
                }

                continue;
            }

            $flat[(string) $key] = (string) $value;
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function character(array $settings, string $key, string $fallback): string
    {
        $declared = $settings[$key] ?? null;

        return is_string($declared) && strlen($declared) === 1 ? $declared : $fallback;
    }
}
