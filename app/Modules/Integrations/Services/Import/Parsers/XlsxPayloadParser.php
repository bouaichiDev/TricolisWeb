<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import\Parsers;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Un classeur `.xlsx` : la première feuille, sa première ligne comme en-tête.
 *
 * Lu par PhpSpreadsheet, jamais par un analyseur maison. Ce qui est tenu :
 *
 * - **aucune formule n'est calculée** : la valeur retenue est celle que le
 *   classeur a enregistrée. Calculer exposerait des fonctions comme
 *   `WEBSERVICE`, qui feraient appeler le réseau par un fichier client ;
 * - aucune macro n'est lue ni exécutée (le lecteur ne les charge pas) ;
 * - le lecteur refuse lui-même les DTD dans les XML internes du classeur ;
 * - le nombre de lignes lues est plafonné.
 *
 * Une date saisie comme date dans Excel devient `AAAA-MM-JJ` (avec l'heure si
 * elle en porte une) ; un nombre garde sa forme décimale, sans flottant bruité.
 */
final readonly class XlsxPayloadParser implements PayloadParser
{
    public function __construct(private int $maxRows = 5000) {}

    public function parse(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'tricolis-xlsx-');

        if ($path === false) {
            throw new RuntimeException('Le classeur n’a pas pu être préparé pour la lecture.');
        }

        try {
            file_put_contents($path, $contents);

            return $this->rows($this->sheet($path));
        } finally {
            @unlink($path);
        }
    }

    public function unwrapsRoot(): bool
    {
        return false;
    }

    private function sheet(string $path): Worksheet
    {
        $reader = new Xlsx;

        if (! $reader->canRead($path)) {
            throw new RuntimeException('Ce fichier n’est pas un classeur Excel .xlsx lisible.');
        }

        $reader->setReadEmptyCells(false);

        try {
            return $reader->load($path)->getSheet(0);
        } catch (ReaderException|Throwable) {
            throw new RuntimeException('Ce classeur Excel est illisible ou endommagé.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Worksheet $sheet): array
    {
        $header = null;
        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $values = [];

            foreach ($row->getCellIterator() as $cell) {
                $values[$cell->getColumn()] = $this->value($cell);
            }

            if ($header === null) {
                $header = array_filter(array_map(
                    static fn (mixed $name): string => trim((string) $name),
                    $values,
                ), static fn (string $name): bool => $name !== '');

                continue;
            }

            if (array_filter($values, static fn (mixed $value): bool => $value !== null && $value !== '') === []) {
                continue;
            }

            if (count($rows) >= $this->maxRows) {
                throw new RuntimeException("Ce classeur dépasse {$this->maxRows} lignes : découpez-le.");
            }

            $record = [];

            foreach ($header as $column => $name) {
                $record[$name] = $values[$column] ?? null;
            }

            $rows[] = $record;
        }

        if ($header === null || $header === []) {
            throw new RuntimeException('Ce classeur ne porte aucune ligne d’en-tête.');
        }

        return $rows;
    }

    private function value(Cell $cell): mixed
    {
        $value = $cell->getDataType() === DataType::TYPE_FORMULA
            ? $cell->getOldCalculatedValue()
            : $cell->getValue();

        if (is_numeric($value) && ! is_string($value) && Date::isDateTime($cell, $value)) {
            $date = Date::excelToDateTimeObject((float) $value);

            return $date->format('H:i:s') === '00:00:00' ? $date->format('Y-m-d') : $date->format('Y-m-d H:i:s');
        }

        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : null;
        }

        return $value;
    }
}
