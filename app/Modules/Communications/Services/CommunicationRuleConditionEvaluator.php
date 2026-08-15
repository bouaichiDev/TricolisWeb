<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

use Illuminate\Validation\ValidationException;

/**
 * Structure et évaluation des conditions d'une règle.
 *
 * Aucun schéma de conditions n'est défini dans les diagrammes ni dans les
 * prompts des Phases 1 à 8. Le §16 impose alors de « documenter le point et ne
 * pas inventer des opérateurs complexes ». Le schéma retenu est donc
 * volontairement minimal — une **conjonction plate** :
 *
 * ```json
 * {"all": [{"field": "order_status", "operator": "eq", "value": "confirmed"}]}
 * ```
 *
 * Pas de `any`, pas de `not`, pas d'imbrication, pas d'expression.
 *
 * L'évaluation **n'accède à aucun modèle**, n'exécute aucun appel de méthode et
 * n'ouvre aucune base : elle compare des faits scalaires déjà extraits. C'est ce
 * qui la rend déterministe et testable sans base de données.
 */
final readonly class CommunicationRuleConditionEvaluator
{
    /** @var list<string> */
    public const array OPERATORS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in'];

    private const string FIELD_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    private const int MAX_CONDITIONS = 20;

    /**
     * Valide la structure et la retourne normalisée.
     *
     * @return array{all: list<array{field: string, operator: string, value: mixed}>}|null
     *
     * @throws ValidationException
     */
    public function validate(mixed $conditions, string $field = 'conditions'): ?array
    {
        if ($conditions === null) {
            return null;
        }

        if (! is_array($conditions) || array_keys($conditions) !== ['all']) {
            $this->fail($field, 'Les conditions doivent contenir une unique clé « all ».');
        }

        $clauses = $conditions['all'];

        if (! is_array($clauses) || ! array_is_list($clauses)) {
            $this->fail("{$field}.all", 'La clé « all » doit contenir une liste de conditions.');
        }

        if (count($clauses) > self::MAX_CONDITIONS) {
            $this->fail("{$field}.all", 'Une règle ne peut pas porter plus de '.self::MAX_CONDITIONS.' conditions.');
        }

        $normalized = [];

        foreach ($clauses as $index => $clause) {
            $normalized[] = $this->validateClause($clause, "{$field}.all.{$index}");
        }

        return ['all' => $normalized];
    }

    /**
     * @return array{field: string, operator: string, value: mixed}
     */
    private function validateClause(mixed $clause, string $path): array
    {
        if (! is_array($clause) || ! isset($clause['field'], $clause['operator']) || ! array_key_exists('value', $clause)) {
            $this->fail($path, 'Une condition doit porter les clés « field », « operator » et « value ».');
        }

        if (! is_string($clause['field']) || preg_match(self::FIELD_PATTERN, $clause['field']) !== 1) {
            $this->fail("{$path}.field", 'Un nom de champ doit être en minuscules, sans point ni espace.');
        }

        if (! is_string($clause['operator']) || ! in_array($clause['operator'], self::OPERATORS, true)) {
            $this->fail("{$path}.operator", 'Opérateur inconnu. Valeurs acceptées : '.implode(', ', self::OPERATORS).'.');
        }

        $listOperator = in_array($clause['operator'], ['in', 'not_in'], true);
        $value = $clause['value'];

        if ($listOperator && (! is_array($value) || ! array_is_list($value) || $value === [])) {
            $this->fail("{$path}.value", 'Les opérateurs « in » et « not_in » attendent une liste non vide.');
        }

        if ($listOperator) {
            foreach ($value as $item) {
                if ($item !== null && ! is_scalar($item)) {
                    $this->fail("{$path}.value", 'Une liste de valeurs ne peut contenir que des valeurs simples.');
                }
            }
        } elseif ($value !== null && ! is_scalar($value)) {
            $this->fail("{$path}.value", 'Une valeur de condition doit être simple : texte, nombre ou booléen.');
        }

        return ['field' => $clause['field'], 'operator' => $clause['operator'], 'value' => $value];
    }

    /**
     * Évalue des conditions déjà validées contre un jeu de faits scalaires.
     *
     * Un champ absent des faits fait échouer la condition : mieux vaut ne pas
     * communiquer que communiquer sur une donnée qu'on n'a pas.
     *
     * @param  array{all: list<array{field: string, operator: string, value: mixed}>}|null  $conditions
     * @param  array<string, scalar|null>  $facts
     */
    public function passes(?array $conditions, array $facts): bool
    {
        if ($conditions === null || $conditions['all'] === []) {
            return true;
        }

        foreach ($conditions['all'] as $clause) {
            if (! array_key_exists($clause['field'], $facts)) {
                return false;
            }

            if (! $this->compare($facts[$clause['field']], $clause['operator'], $clause['value'])) {
                return false;
            }
        }

        return true;
    }

    private function compare(string|int|float|bool|null $fact, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'eq' => $fact == $expected,
            'neq' => $fact != $expected,
            'gt' => $fact !== null && $fact > $expected,
            'gte' => $fact !== null && $fact >= $expected,
            'lt' => $fact !== null && $fact < $expected,
            'lte' => $fact !== null && $fact <= $expected,
            'in' => is_array($expected) && in_array($fact, $expected, false),
            'not_in' => is_array($expected) && ! in_array($fact, $expected, false),
            default => false,
        };
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
