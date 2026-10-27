<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Mapping;

/**
 * Lecture d'une valeur dans un enregistrement source.
 *
 * Le point permet d'atteindre une valeur imbriquée — `client.reference` dans
 * un JSON, `Header.OrderNo` dans un XML. Une colonne CSV portant un point est
 * cherchée telle quelle en premier : son nom prime sur l'interprétation du
 * séparateur.
 */
final class SourcePath
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function value(array $row, string $path): mixed
    {
        if (array_key_exists($path, $row)) {
            return self::clean($row[$path]);
        }

        $current = self::walk($row, $path);

        return $current === null ? null : self::clean($current);
    }

    /**
     * La sous-collection désignée, toujours sous forme de liste.
     *
     * Un XML ne distingue pas « un article » de « une liste d'un article » :
     * l'élément unique arrive en objet, et il est rendu en liste d'un élément.
     *
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    public static function collection(array $row, string $path): array
    {
        $found = array_key_exists($path, $row) ? $row[$path] : self::walk($row, $path);

        if (! is_array($found) || $found === []) {
            return [];
        }

        $items = array_is_list($found) ? $found : [$found];

        return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
    }

    private static function walk(mixed $current, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Une cellule vide n'est pas une valeur.
     *
     * Un CSV rend `''` pour une colonne non renseignée. La conserver
     * transformerait un champ absent en chaîne vide, que la validation
     * accepterait là où elle aurait dû réclamer une valeur.
     */
    private static function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return $value;
    }
}
