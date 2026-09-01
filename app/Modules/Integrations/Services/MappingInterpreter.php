<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

/**
 * Applique une correspondance à des lignes de fichier.
 *
 * La convention n'est pas inventée ici : c'est celle que l'écran de
 * configuration produit déjà. **La correspondance a la forme de la charge utile
 * attendue, et chaque feuille porte le nom du champ source.**
 *
 * ```json
 * {
 *   "externalReference": "REF_CDE",
 *   "lines": [{ "name": "ArtName", "quantity": "QTE" }],
 *   "packages": [{ "key": "ColisRef" }]
 * }
 * ```
 *
 * Trois règles, et rien d'autre :
 *
 * 1. **Une chaîne est un nom de colonne.** Absente du fichier, la clé est
 *    omise — pas mise à `null`. La validation dira alors ce qui manque, au lieu
 *    de laisser passer un vide qui ressemble à une valeur.
 * 2. **Un tableau est un modèle**, appliqué à chaque ligne. Son premier élément
 *    décrit ce qu'une ligne produit ; les suivants sont ignorés, un modèle n'en
 *    a qu'un.
 * 3. **Un objet est un objet.** Il se construit à partir de la même ligne.
 *
 * Les éléments **identiques sont fusionnés**. C'est ce qui fait qu'un fichier
 * de trois lignes partageant `ColisRef` produit un colis et non trois : le
 * mapping d'un colis ne retient que ce qui l'identifie, donc trois lignes du
 * même colis donnent trois fois le même objet. Aucune clé de regroupement n'est
 * à déclarer — c'est le contenu qui décide.
 *
 * **Rien n'est exécuté.** Les valeurs sont recopiées, jamais interprétées : pas
 * d'expression, pas de fonction, pas de calcul. Le §10 l'exige, et une
 * correspondance n'a pas à être du code.
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
            if (is_string($source)) {
                // Un champ scalaire vaut pour la commande entière : il est lu
                // sur la première ligne. Un fichier dont les lignes le
                // contrediraient décrirait deux commandes, pas une.
                $value = $this->valueOf($rows[0], $source);

                if ($value !== null) {
                    $result[$target] = $value;
                }

                continue;
            }

            if (! is_array($source) || $source === []) {
                continue;
            }

            if (array_is_list($source)) {
                $collection = $this->collect($source[0], $rows);

                if ($collection !== []) {
                    $result[$target] = $collection;
                }

                continue;
            }

            $nested = $this->apply($source, $rows);

            if ($nested !== []) {
                $result[$target] = $nested;
            }
        }

        return $result;
    }

    /**
     * Un modèle appliqué à chaque ligne, les doublons fondus.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<mixed>
     */
    private function collect(mixed $template, array $rows): array
    {
        if (! is_array($template)) {
            return [];
        }

        $built = [];

        foreach ($rows as $row) {
            $element = $this->apply($template, [$row]);

            if ($element === []) {
                continue;
            }

            // La comparaison porte sur le contenu : deux lignes qui décrivent
            // le meme colis produisent le meme objet, et n'en font qu'un.
            $signature = json_encode($element);

            if (! array_key_exists($signature, $built)) {
                $built[$signature] = $element;
            }
        }

        return array_values($built);
    }

    /**
     * Valeur d'une colonne dans une ligne.
     *
     * Le point permet d'atteindre une valeur imbriquée d'un fichier JSON —
     * `client.reference` — sans quoi un fichier structuré serait illisible. Une
     * colonne CSV portant un point est cherchée telle quelle en premier : son
     * nom prime sur l'interprétation du séparateur.
     *
     * @param  array<string, mixed>  $row
     */
    private function valueOf(array $row, string $path): mixed
    {
        if (array_key_exists($path, $row)) {
            return $this->clean($row[$path]);
        }

        $current = $row;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $this->clean($current);
    }

    /**
     * Une cellule vide n'est pas une valeur.
     *
     * Un CSV rend `''` pour une colonne non renseignée. La conserver
     * transformerait un champ absent en chaîne vide, que la validation
     * accepterait là où elle aurait dû réclamer une valeur.
     */
    private function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return $value;
    }
}
