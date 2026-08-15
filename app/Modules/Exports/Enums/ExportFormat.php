<?php

declare(strict_types=1);

namespace App\Modules\Exports\Enums;

/**
 * Format d'un export client.
 *
 * Exactement les quatre valeurs du diagramme (lignes 70-75). `XLS`, `XLSX`,
 * `TXT`, `ZIP` et `EDI` ne figurent pas au modèle et ne sont pas ajoutés.
 */
enum ExportFormat: string
{
    case XML = 'xml';
    case CSV = 'csv';
    case JSON = 'json';
    case PDF = 'pdf';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
