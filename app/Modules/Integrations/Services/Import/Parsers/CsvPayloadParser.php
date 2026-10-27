<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import\Parsers;

use RuntimeException;

/**
 * Un CSV, lu en lignes « nom de colonne → valeur ».
 *
 * Le séparateur est déduit de l'en-tête : les exports européens emploient le
 * point-virgule aussi souvent que la virgule.
 */
final readonly class CsvPayloadParser implements PayloadParser
{
    public function parse(string $contents): array
    {
        // Le BOM d'un fichier produit sous Windows collerait à la première
        // colonne, et « REF » deviendrait introuvable au mapping.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Le fichier n’a pas pu être ouvert.');
        }

        fwrite($handle, $contents);
        rewind($handle);

        $delimiter = $this->delimiterOf($contents);
        $header = fgetcsv($handle, 0, $delimiter, '"', '\\');

        if ($header === false || $header === [null]) {
            fclose($handle);

            throw new RuntimeException('Ce fichier CSV ne porte aucune ligne d’en-tête.');
        }

        $columns = array_map(static fn ($name): string => trim((string) $name), $header);
        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            // Une ligne vide en fin de fichier n'est pas un enregistrement.
            if ($line === [null] || $line === ['']) {
                continue;
            }

            $row = [];

            foreach ($columns as $position => $column) {
                $row[$column] = $line[$position] ?? null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    public function unwrapsRoot(): bool
    {
        return false;
    }

    private function delimiterOf(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n");

        if ($firstLine === false) {
            return ',';
        }

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }
}
