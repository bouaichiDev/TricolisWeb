<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import;

use App\Modules\Integrations\Services\Mapping\MappingDirectives;
use App\Modules\Integrations\Services\Mapping\SourcePath;

/**
 * Découpe des enregistrements en commandes.
 *
 * Un fichier plat (CSV, Excel) porte souvent **plusieurs** commandes sur
 * plusieurs lignes : elles se reconnaissent à leur référence — les lignes qui
 * partagent la même valeur de la colonne mappée sur `externalReference`
 * forment une commande. Un document structuré (JSON, XML) porte une commande
 * par enregistrement, avec ses articles imbriqués : la même règle y donne un
 * groupe par enregistrement.
 *
 * Sans référence mappée, tout le contenu décrit **une** commande — le cas
 * d'un export ne contenant qu'un envoi.
 */
final readonly class OrderGroups
{
    /**
     * @param  array<string, mixed>  $mapping
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<array<string, mixed>>>
     */
    public function split(array $mapping, array $rows): array
    {
        $column = $this->referenceColumn($mapping['externalReference'] ?? null);

        if ($column === null) {
            return $rows === [] ? [] : [$rows];
        }

        $groups = [];

        foreach ($rows as $row) {
            // Une ligne sans référence n'est pas rattachable : elle rejoint le
            // groupe sans nom plutôt que d'être perdue en silence.
            $value = SourcePath::value($row, $column);
            $groups[is_scalar($value) ? (string) $value : ''][] = $row;
        }

        return array_values($groups);
    }

    private function referenceColumn(mixed $mapped): ?string
    {
        if (is_string($mapped)) {
            return $mapped;
        }

        $source = is_array($mapped) ? ($mapped[MappingDirectives::SOURCE] ?? null) : null;

        return is_string($source) ? $source : null;
    }
}
