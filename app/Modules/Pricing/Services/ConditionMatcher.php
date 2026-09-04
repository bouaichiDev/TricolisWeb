<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\Models\PriceRule;
use App\Modules\Pricing\Models\PriceRuleCondition;

/**
 * Décide si les conditions d'une règle sont remplies.
 *
 * **Toutes, pas une.** Une règle qui dit « livraison » *et* « code postal entre
 * 1144 et 4000 » ne s'applique qu'aux deux à la fois ; les traiter en « ou »
 * ferait facturer une livraison zurichoise au tarif genevois.
 *
 * Une condition portant sur une dimension absente du contexte **échoue** plutôt
 * que d'être ignorée : une règle réservée à une zone ne doit pas s'appliquer à
 * une prestation sans adresse.
 */
final readonly class ConditionMatcher
{
    /** @var list<string> */
    public const array OPERATORS = ['=', '!=', '<', '<=', '>', '>=', 'between', 'in', 'starts_with'];

    /**
     * @param  array<string, string|null>  $context
     */
    public function matches(PriceRule $rule, array $context): bool
    {
        foreach ($rule->conditions as $condition) {
            if (! $this->satisfied($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private function satisfied(PriceRuleCondition $condition, array $context): bool
    {
        $value = $context[$condition->variable] ?? null;

        if ($value === null || $value === '') {
            return false;
        }

        $from = (string) $condition->value_from;
        $to = $condition->value_to;

        return match ($condition->operator) {
            '=' => $this->equals($value, $from),
            '!=' => ! $this->equals($value, $from),
            'starts_with' => str_starts_with($value, $from),
            // Une liste separee par des virgules : « DELIVERY, PICKUP ».
            'in' => in_array(
                mb_strtolower($value),
                array_map(
                    static fn (string $entry): string => mb_strtolower(trim($entry)),
                    explode(',', $from),
                ),
                true,
            ),
            'between' => $this->numeric($value, $from, '>=') && $this->numeric($value, (string) $to, '<='),
            default => $this->numeric($value, $from, $condition->operator),
        };
    }

    /** Comparaison de texte insensible à la casse : un code service se saisit
     *  tantôt en majuscules, tantôt non. */
    private function equals(string $value, string $expected): bool
    {
        return mb_strtolower($value) === mb_strtolower(trim($expected));
    }

    /**
     * Comparaison numérique, qui refuse ce qui n'est pas un nombre.
     *
     * Comparer « GE » à « 1200 » avec `<` donnerait un résultat en PHP ; il ne
     * voudrait rien dire, et une règle mal saisie s'appliquerait au hasard.
     */
    private function numeric(string $value, string $bound, string $operator): bool
    {
        if (! is_numeric($value) || ! is_numeric($bound)) {
            return false;
        }

        $left = (float) $value;
        $right = (float) $bound;

        return match ($operator) {
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            default => false,
        };
    }
}
