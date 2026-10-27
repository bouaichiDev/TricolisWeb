<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Services\Mapping\MappingDirectives;
use App\Modules\Integrations\Services\Mapping\SourcePath;
use App\Modules\Integrations\Services\Mapping\ValueCaster;

/**
 * Applique une correspondance à des lignes de fichier.
 *
 * **La correspondance a la forme de la charge utile attendue, et chaque
 * feuille porte le nom du champ source.**
 *
 * ```json
 * {
 *   "externalReference": "REF_CDE",
 *   "lines": [{ "name": "ArtName", "quantity": "QTE" }],
 *   "packages": [{ "key": "ColisRef" }]
 * }
 * ```
 *
 * Trois règles d'origine :
 *
 * 1. **Une chaîne est un nom de colonne.** Absente du fichier, la clé est
 *    omise — pas mise à `null`. La validation dira alors ce qui manque.
 * 2. **Un tableau est un modèle**, appliqué à chaque ligne — ou, avec `@each`,
 *    à chaque élément d'une sous-collection de la ligne (les articles d'une
 *    commande XML ou JSON).
 * 3. **Un objet est un objet**, sauf s'il porte `@source` ou `@value` : c'est
 *    alors une feuille enrichie (défaut, conversion). Voir `MappingDirectives`.
 *
 * Les éléments **identiques sont fusionnés** : trois lignes du même colis
 * donnent trois fois le même objet, donc un seul colis.
 *
 * **Rien n'est exécuté.** Les valeurs sont recopiées ou converties par une
 * liste fermée de types — jamais interprétées comme du code.
 */
final readonly class MappingInterpreter
{
    /**
     * @param  array<string, mixed>  $mapping
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function apply(array $mapping, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $result = [];

        foreach ($mapping as $target => $source) {
            if (MappingDirectives::isDirective($target)) {
                continue;
            }

            $value = $this->resolve($source, $rows);

            if ($value !== null && $value !== []) {
                $result[$target] = $value;
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function resolve(mixed $source, array $rows): mixed
    {
        // Un champ scalaire vaut pour la commande entière : il est lu sur la
        // première ligne. Des lignes qui le contrediraient décriraient deux
        // commandes, pas une.
        if (is_string($source)) {
            return SourcePath::value($rows[0], $source);
        }

        if (! is_array($source) || $source === []) {
            return null;
        }

        if (MappingDirectives::isLeaf($source)) {
            return $this->leaf($source, $rows[0]);
        }

        if (array_is_list($source)) {
            return $this->collect($source[0], $rows);
        }

        return $this->apply($source, $rows);
    }

    /**
     * Une feuille enrichie : constante ou colonne, défaut, conversion.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $row
     */
    private function leaf(array $node, array $row): mixed
    {
        $value = array_key_exists(MappingDirectives::VALUE, $node)
            ? $node[MappingDirectives::VALUE]
            : SourcePath::value($row, (string) $node[MappingDirectives::SOURCE]);

        if (($value === null || $value === '') && array_key_exists(MappingDirectives::DEFAULT, $node)) {
            $value = $node[MappingDirectives::DEFAULT];
        }

        $type = $node[MappingDirectives::TYPE] ?? null;

        return is_string($type) ? ValueCaster::cast($value, $type) : $value;
    }

    /**
     * Un modèle appliqué à chaque ligne — ou à chaque élément d'une
     * sous-collection — les doublons fondus.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<mixed>
     */
    private function collect(mixed $template, array $rows): array
    {
        if (! is_array($template)) {
            return [];
        }

        $each = $template[MappingDirectives::EACH] ?? null;
        $built = [];

        foreach ($rows as $row) {
            foreach ($this->itemsOf($row, $each) as $item) {
                $element = $this->apply($template, [$item]);

                if ($element === []) {
                    continue;
                }

                // La comparaison porte sur le contenu : deux lignes qui
                // décrivent le même colis n'en font qu'un.
                $built[json_encode($element)] ??= $element;
            }
        }

        return array_values($built);
    }

    /**
     * Les éléments qu'un modèle parcourt dans une ligne.
     *
     * Sans `@each`, la ligne elle-même. Avec, chaque élément de la
     * sous-collection, qui garde l'accès à son parent sous `^`.
     *
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function itemsOf(array $row, mixed $each): array
    {
        if (! is_string($each) || $each === '') {
            return [$row];
        }

        return array_map(
            static fn (array $item): array => $item + [MappingDirectives::PARENT => $row],
            SourcePath::collection($row, $each),
        );
    }
}
