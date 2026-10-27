<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Mapping;

/**
 * Une correspondance mise à plat pour l'affichage : « champ source → champ
 * Tricolis », une ligne par feuille.
 *
 * `lines[].quantity ← items[].Qty` : le chemin cible garde la notation `[]`
 * de la liste de champs, la source celle du document (`@each` compris). Une
 * constante est signalée comme telle, pas comme un nom de colonne.
 */
final class MappingPairs
{
    /**
     * @param  array<mixed>|null  $mapping
     * @return list<array{target: string, source: string|null, constant: scalar|null, type: string|null}>
     */
    public static function of(?array $mapping): array
    {
        return $mapping === null ? [] : self::walk($mapping, '', '');
    }

    /**
     * @param  array<mixed>  $node
     * @return list<array{target: string, source: string|null, constant: scalar|null, type: string|null}>
     */
    private static function walk(array $node, string $targetPrefix, string $sourcePrefix): array
    {
        $pairs = [];

        foreach ($node as $key => $value) {
            if (MappingDirectives::isDirective($key)) {
                continue;
            }

            $target = $targetPrefix.$key;

            if (is_string($value)) {
                $pairs[] = ['target' => $target, 'source' => $sourcePrefix.$value, 'constant' => null, 'type' => null];
            } elseif (is_array($value) && MappingDirectives::isLeaf($value)) {
                $constant = $value[MappingDirectives::VALUE] ?? null;
                $source = $value[MappingDirectives::SOURCE] ?? null;

                $pairs[] = [
                    'target' => $target,
                    'source' => is_string($source) ? $sourcePrefix.$source : null,
                    'constant' => is_scalar($constant) ? $constant : null,
                    'type' => is_string($value[MappingDirectives::TYPE] ?? null) ? $value[MappingDirectives::TYPE] : null,
                ];
            } elseif (is_array($value) && array_is_list($value) && is_array($value[0] ?? null)) {
                $each = $value[0][MappingDirectives::EACH] ?? null;
                $pairs = [...$pairs, ...self::walk($value[0], $target.'[].', is_string($each) ? $sourcePrefix.$each.'[].' : $sourcePrefix)];
            } elseif (is_array($value) && $value !== []) {
                $pairs = [...$pairs, ...self::walk($value, $target.'.', $sourcePrefix)];
            }
        }

        return $pairs;
    }
}
