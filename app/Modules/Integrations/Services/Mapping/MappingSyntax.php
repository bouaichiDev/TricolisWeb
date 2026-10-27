<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Mapping;

/**
 * Contrôle et diagnostic d'une correspondance.
 *
 * Deux niveaux, volontairement distincts :
 *
 * - **les erreurs** (`problems`) portent sur les directives : une directive
 *   inconnue, un type hors liste, `@source` et `@value` à la fois. Elles
 *   empêchent l'enregistrement — le moteur ne saurait pas quoi faire ;
 * - **le diagnostic** (`diagnose`) signale les cibles inconnues et les champs
 *   obligatoires non alimentés. Il informe sans bloquer : une correspondance se
 *   construit par étapes, et des configurations plus anciennes emploient des
 *   noms libres que l'enregistrement a toujours acceptés.
 */
final class MappingSyntax
{
    private const int MAX_DEPTH = 8;

    /**
     * @param  array<mixed>  $mapping
     * @return list<string>
     */
    public static function problems(array $mapping): array
    {
        $problems = [];

        foreach ($mapping as $key => $value) {
            if ($key === MappingDirectives::RECORDS) {
                if (! is_string($value)) {
                    $problems[] = '@records doit être un chemin (texte).';
                }

                continue;
            }

            self::node((string) $key, $value, 1, $problems);
        }

        return $problems;
    }

    /**
     * @param  array<mixed>  $mapping
     * @return array{mapped: list<string>, unknownTargets: list<string>, missingRequired: list<string>}
     */
    public static function diagnose(array $mapping): array
    {
        $mapped = self::targets($mapping, '');
        $known = ImportTargetFields::paths();
        $required = [];

        foreach (ImportTargetFields::groups() as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['requirement'] === 'required' && ! str_contains($field['path'], '.recipient.')
                    && ! str_contains($field['path'], '[].packages[].')) {
                    $required[] = $field['path'];
                }
            }
        }

        return [
            'mapped' => $mapped,
            'unknownTargets' => array_values(array_diff($mapped, $known)),
            'missingRequired' => array_values(array_diff($required, $mapped)),
        ];
    }

    /**
     * @param  list<string>  $problems
     */
    private static function node(string $path, mixed $value, int $depth, array &$problems): void
    {
        if ($depth > self::MAX_DEPTH) {
            $problems[] = "{$path} : imbrication trop profonde.";

            return;
        }

        if (is_string($value) || $value === null || ! is_array($value)) {
            return;
        }

        if (MappingDirectives::isLeaf($value)) {
            self::leaf($path, $value, $problems);

            return;
        }

        if (array_is_list($value)) {
            $template = $value[0] ?? null;

            if (is_array($template)) {
                if (isset($template[MappingDirectives::EACH]) && ! is_string($template[MappingDirectives::EACH])) {
                    $problems[] = "{$path}[] : @each doit être un chemin (texte).";
                }

                foreach ($template as $key => $child) {
                    if ($key !== MappingDirectives::EACH) {
                        self::child("{$path}[]", $key, $child, $depth, $problems);
                    }
                }
            }

            return;
        }

        foreach ($value as $key => $child) {
            self::child($path, $key, $child, $depth, $problems);
        }
    }

    /**
     * @param  list<string>  $problems
     */
    private static function child(string $parent, int|string $key, mixed $value, int $depth, array &$problems): void
    {
        if (MappingDirectives::isDirective($key)) {
            $problems[] = "{$parent} : directive « {$key} » inconnue à cet endroit.";

            return;
        }

        self::node("{$parent}.{$key}", $value, $depth + 1, $problems);
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<string>  $problems
     */
    private static function leaf(string $path, array $node, array &$problems): void
    {
        foreach (array_keys($node) as $key) {
            if (! in_array($key, MappingDirectives::LEAF_KEYS, true)) {
                $problems[] = "{$path} : « {$key} » n’est pas une directive de champ.";
            }
        }

        if (array_key_exists(MappingDirectives::SOURCE, $node) && array_key_exists(MappingDirectives::VALUE, $node)) {
            $problems[] = "{$path} : @source et @value s’excluent.";
        }

        if (array_key_exists(MappingDirectives::SOURCE, $node) && ! is_string($node[MappingDirectives::SOURCE])) {
            $problems[] = "{$path} : @source doit être un nom de colonne.";
        }

        foreach ([MappingDirectives::VALUE, MappingDirectives::DEFAULT] as $directive) {
            if (array_key_exists($directive, $node) && ! is_scalar($node[$directive]) && $node[$directive] !== null) {
                $problems[] = "{$path} : {$directive} doit être une valeur simple.";
            }
        }

        $type = $node[MappingDirectives::TYPE] ?? null;

        if ($type !== null && ! in_array($type, MappingDirectives::TYPES, true)) {
            $problems[] = "{$path} : type « ".(is_scalar($type) ? $type : '?').' » non pris en charge.';
        }
    }

    /**
     * @param  array<mixed>  $mapping
     * @return list<string>
     */
    private static function targets(array $mapping, string $prefix): array
    {
        $paths = [];

        foreach ($mapping as $key => $value) {
            if (MappingDirectives::isDirective($key)) {
                continue;
            }

            $path = $prefix.$key;

            if (is_string($value) || (is_array($value) && MappingDirectives::isLeaf($value))) {
                $paths[] = $path;
            } elseif (is_array($value) && array_is_list($value) && is_array($value[0] ?? null)) {
                $paths = [...$paths, ...self::targets($value[0], $path.'[].')];
            } elseif (is_array($value) && $value !== []) {
                $paths = [...$paths, ...self::targets($value, $path.'.')];
            }
        }

        return $paths;
    }
}
