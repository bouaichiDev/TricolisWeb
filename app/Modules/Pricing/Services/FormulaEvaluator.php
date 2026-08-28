<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\Exceptions\InvalidFormula;

/**
 * Évalue l'arbre d'une formule, en décimal.
 *
 * **Pas de flottants.** Le §169J l'exige, et la raison est concrète :
 * `0.1 + 0.2` vaut `0.30000000000000004` en flottant, et une facture qui se
 * relit à un centime près n'est plus la même facture. Les calculs passent par
 * BCMath, déjà présent dans PHP — aucune dépendance ajoutée.
 *
 * Le calcul travaille à six décimales puis arrondit à deux : arrondir à chaque
 * étape ferait dériver une division suivie d'une multiplication, ce qui est
 * exactement la forme de « par tranche de 100 kg ».
 *
 * L'arrondi final est **au plus proche**, à mi-chemin vers le haut — la règle
 * commerciale usuelle, celle qu'un client retrouve sur son relevé.
 */
final readonly class FormulaEvaluator
{
    /** Décimales de travail : au-delà, on additionne du bruit. */
    private const int SCALE = 6;

    private const int MONEY_SCALE = 2;

    /** Un montant au-delà tient du défaut de saisie, pas du tarif. */
    private const string MAX_ABSOLUTE = '999999999';

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, string|float|int|null>  $variables
     */
    public function evaluate(array $node, array $variables): string
    {
        $raw = $this->walk($node, $variables);

        if (bccomp($this->absolute($raw), self::MAX_ABSOLUTE, self::SCALE) === 1) {
            throw InvalidFormula::outOfRange();
        }

        return $this->round($raw);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, string|float|int|null>  $variables
     */
    private function walk(array $node, array $variables): string
    {
        if ($node['type'] === 'number') {
            return (string) $node['value'];
        }

        if ($node['type'] === 'variable') {
            return $this->value($node['name'], $variables);
        }

        $left = $this->walk($node['left'], $variables);
        $right = $this->walk($node['right'], $variables);

        return match ($node['operator']) {
            '+' => bcadd($left, $right, self::SCALE),
            '-' => bcsub($left, $right, self::SCALE),
            '*' => bcmul($left, $right, self::SCALE),
            '/' => $this->divide($left, $right),
            default => throw InvalidFormula::syntax("opérateur « {$node['operator']} » inconnu"),
        };
    }

    /**
     * @param  array<string, string|float|int|null>  $variables
     */
    private function value(string $name, array $variables): string
    {
        $value = $variables[$name] ?? null;

        // Une variable absente n'est pas zero : le §169I refuse un tarif
        // approximatif, et une prestation sans poids ne coute pas 0 franc.
        if ($value === null || $value === '') {
            throw InvalidFormula::missingValue($name);
        }

        if (! is_numeric($value)) {
            throw InvalidFormula::missingValue($name);
        }

        return number_format((float) $value, self::SCALE, '.', '');
    }

    private function divide(string $left, string $right): string
    {
        if (bccomp($right, '0', self::SCALE) === 0) {
            throw InvalidFormula::divisionByZero();
        }

        return bcdiv($left, $right, self::SCALE);
    }

    /** Arrondi commercial : au plus proche, la moitié vers le haut. */
    private function round(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = $this->absolute($value);

        $shifted = bcadd($absolute, '0.005', self::SCALE);
        $rounded = bcadd($shifted, '0', self::MONEY_SCALE);

        return $negative && bccomp($rounded, '0', self::MONEY_SCALE) !== 0
            ? '-'.$rounded
            : $rounded;
    }

    private function absolute(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }
}
