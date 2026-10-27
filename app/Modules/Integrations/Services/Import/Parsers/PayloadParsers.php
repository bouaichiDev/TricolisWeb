<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import\Parsers;

use RuntimeException;

/**
 * Le lecteur qui correspond à un `fileFormat`.
 *
 * `fileFormat` est une **chaîne libre** : le modèle n'en énumère aucune. La
 * comparaison est donc tolérante — `xlsx`, `excel`, `XLSX` désignent la même
 * chose — et un format inconnu est refusé en le nommant, plutôt que deviné.
 */
final readonly class PayloadParsers
{
    public function for(string $fileFormat): PayloadParser
    {
        return match (self::kind($fileFormat)) {
            'xlsx' => new XlsxPayloadParser((int) config('customer_integrations.files.max_rows', 5000)),
            'xml' => new XmlPayloadParser((int) config('customer_integrations.files.xml_max_depth', 32)),
            'json' => new JsonPayloadParser,
            'csv' => new CsvPayloadParser,
            default => throw new RuntimeException(
                "Le format « {$fileFormat} » n’est pas lisible : seuls CSV, JSON, XML et Excel (.xlsx) le sont.",
            ),
        };
    }

    /**
     * Le format reconnu, ou `null`.
     */
    public static function kind(string $fileFormat): ?string
    {
        $format = strtolower(trim($fileFormat));

        return match (true) {
            str_contains($format, 'xlsx'), str_contains($format, 'excel'), str_contains($format, 'spreadsheet') => 'xlsx',
            str_contains($format, 'xml') => 'xml',
            str_contains($format, 'json') => 'json',
            str_contains($format, 'csv') => 'csv',
            default => null,
        };
    }
}
