<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use JsonException;
use RuntimeException;

/**
 * Lit un fichier client et le rend sous forme de **lignes**.
 *
 * Une ligne est un tableau associatif : nom de colonne vers valeur. C'est la
 * seule forme que l'interpréteur de correspondance comprend, et elle convient
 * aussi bien à un CSV — une ligne par enregistrement — qu'à un JSON qui porte
 * un tableau d'objets.
 *
 * Le format vient de `fileFormat`, une **chaîne libre** : le modèle n'en
 * énumère aucune, et le §9 interdit d'en inventer la liste. La comparaison est
 * donc tolérante — `csv`, `CSV`, `text/csv` désignent la même chose — et un
 * format inconnu est refusé en le nommant, plutôt que deviné.
 */
final readonly class ImportSourceReader
{
    /**
     * @return list<array<string, mixed>>
     */
    public function read(string $contents, string $fileFormat): array
    {
        $format = strtolower(trim($fileFormat));

        if (str_contains($format, 'json')) {
            return $this->json($contents);
        }

        if (str_contains($format, 'csv')) {
            return $this->csv($contents);
        }

        throw new RuntimeException(
            "Le format « {$fileFormat} » n’est pas lisible : seuls CSV et JSON le sont aujourd’hui.",
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function json(string $contents): array
    {
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Ce fichier n’est pas un JSON valide : '.$exception->getMessage());
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Un fichier JSON doit contenir un objet ou une liste d’objets.');
        }

        // Un objet seul est une ligne unique : c'est le cas d'un fichier qui
        // décrit une commande, et non un lot.
        if ($this->isAssociative($decoded)) {
            return [$decoded];
        }

        $rows = [];

        foreach ($decoded as $index => $entry) {
            if (! is_array($entry) || ! $this->isAssociative($entry)) {
                throw new RuntimeException("L’élément {$index} de la liste n’est pas un objet.");
            }

            $rows[] = $entry;
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function csv(string $contents): array
    {
        // Le BOM d'un fichier produit sous Windows collerait à la première
        // colonne, et « REF » deviendrait « ﻿REF » — introuvable au mapping.
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

        /** @var list<string> $columns */
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

    /**
     * Séparateur du fichier, déduit de son en-tête.
     *
     * Les exports européens emploient le point-virgule aussi souvent que la
     * virgule, et imposer l'un des deux ferait lire une colonne unique dont le
     * nom contiendrait tout l'en-tête.
     */
    private function delimiterOf(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n");

        if ($firstLine === false) {
            return ',';
        }

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isAssociative(array $value): bool
    {
        return $value !== [] && ! array_is_list($value);
    }
}
