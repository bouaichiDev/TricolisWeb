<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Validation;

use Illuminate\Support\Facades\Validator;

/**
 * Le moteur de `CustomerImportConfiguration.validationRules`.
 *
 * Forme — des champs Tricolis vers des règles, plus deux options préfixées
 * par `@` comme dans la correspondance :
 *
 * ```json
 * {
 *   "externalReference": ["required", "max:64"],
 *   "lines.*.quantity": "numeric|min:1",
 *   "@onDuplicate": "skip",
 *   "@requireReference": true
 * }
 * ```
 *
 * **Seules les règles de `ALLOWED` passent.** Une règle Laravel arbitraire —
 * `regex` (déni de service), `exists` (lecture d'une autre table), une classe
 * — n'est jamais transmise au validateur : le JSON choisit dans une liste, il
 * n'écrit pas de code.
 *
 * Ces règles **s'ajoutent** à celles de `StoreOrderRequest`, jamais ne les
 * remplacent : une configuration peut durcir un import, pas l'assouplir.
 */
final class ImportValidationRules
{
    /** @var list<string> */
    public const array ALLOWED = ['required', 'nullable', 'string', 'numeric', 'integer', 'boolean', 'date', 'email', 'min', 'max', 'size', 'in'];

    /** Règles qui exigent un paramètre numérique. */
    private const array NUMERIC_PARAMETER = ['min', 'max', 'size'];

    /** @var list<string> */
    public const array DUPLICATE_STRATEGIES = ['skip', 'reject', 'update'];

    public const string ON_DUPLICATE = '@onDuplicate';

    public const string REQUIRE_REFERENCE = '@requireReference';

    /**
     * Que faire d'une référence déjà importée : `skip` par défaut.
     *
     * Jamais `update` sans qu'une configuration l'ait demandé : réécrire une
     * commande est une décision métier, pas un comportement implicite.
     *
     * @param  array<mixed>|null  $config
     */
    public static function onDuplicate(?array $config): string
    {
        $strategy = $config[self::ON_DUPLICATE] ?? null;

        return in_array($strategy, self::DUPLICATE_STRATEGIES, true) ? $strategy : 'skip';
    }

    /** @param  array<mixed>|null  $config */
    public static function requiresReference(?array $config): ?bool
    {
        $value = $config[self::REQUIRE_REFERENCE] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * Les erreurs de la charge utile au regard des règles configurées.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<mixed>|null  $config
     * @return array<string, list<string>>
     */
    public static function errors(array $payload, ?array $config): array
    {
        $rules = [];

        foreach ($config ?? [] as $field => $declared) {
            if (is_string($field) && ! str_starts_with($field, '@') && self::fieldProblems($field, $declared) === []) {
                $rules[$field] = self::split($declared);
            }
        }

        return $rules === [] ? [] : Validator::make($payload, $rules)->errors()->toArray();
    }

    /**
     * Ce qui empêche d'enregistrer ces règles.
     *
     * @return list<string>
     */
    public static function problems(mixed $config): array
    {
        if (! is_array($config)) {
            return ['Les règles de validation doivent être un objet.'];
        }

        $problems = [];

        foreach ($config as $field => $declared) {
            $field = (string) $field;

            if ($field === self::ON_DUPLICATE) {
                if (! in_array($declared, self::DUPLICATE_STRATEGIES, true)) {
                    $problems[] = '@onDuplicate : skip, reject ou update.';
                }
            } elseif ($field === self::REQUIRE_REFERENCE) {
                if (! is_bool($declared)) {
                    $problems[] = '@requireReference : vrai ou faux.';
                }
            } elseif (str_starts_with($field, '@')) {
                $problems[] = "Option « {$field} » inconnue.";
            } else {
                $problems = [...$problems, ...self::fieldProblems($field, $declared)];
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private static function fieldProblems(string $field, mixed $declared): array
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9]*(\.(\*|[A-Za-z0-9]+))*$/', $field) !== 1) {
            return ["« {$field} » n’est pas un chemin de champ valide."];
        }

        if (! is_string($declared) && ! (is_array($declared) && array_is_list($declared))) {
            return ["{$field} : règles attendues en texte (a|b) ou en liste."];
        }

        $problems = [];

        foreach (self::split($declared) as $rule) {
            if (! is_string($rule)) {
                $problems[] = "{$field} : une règle doit être un texte.";

                continue;
            }

            [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

            if (! in_array($name, self::ALLOWED, true)) {
                $problems[] = "{$field} : règle « {$name} » non autorisée.";
            } elseif (in_array($name, self::NUMERIC_PARAMETER, true) && ! is_numeric($parameter)) {
                $problems[] = "{$field} : {$name} attend un nombre.";
            } elseif ($name === 'in' && ($parameter === null || $parameter === '')) {
                $problems[] = "{$field} : in attend une liste de valeurs.";
            }
        }

        return $problems;
    }

    /**
     * @return list<mixed>
     */
    private static function split(mixed $declared): array
    {
        return is_string($declared) ? explode('|', $declared) : array_values((array) $declared);
    }
}
