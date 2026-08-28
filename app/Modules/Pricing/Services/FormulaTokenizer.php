<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\Exceptions\InvalidFormula;

/**
 * Découpe une formule en jetons, et refuse tout le reste.
 *
 * **C'est ici que la sécurité se joue.** Le §169G interdit `eval`, et toute
 * exécution de PHP, SQL, JavaScript ou shell venant d'une formule. Plutôt que
 * d'interdire des motifs dangereux — liste qu'on oublie toujours de compléter —
 * le tokenizer n'accepte que ce qu'il connaît : des nombres, des variables
 * `{P:nom}`, des littéraux `{V:nombre}`, quatre opérateurs et des parenthèses.
 * Un caractère inconnu arrête la lecture.
 *
 * La longueur est bornée : une formule d'un mégaoctet n'est pas un tarif, et
 * la parser coûterait plus que de la refuser.
 */
final readonly class FormulaTokenizer
{
    /** Au-delà, ce n'est plus une formule tarifaire. */
    public const int MAX_LENGTH = 500;

    /** @var list<string> */
    public const array OPERATORS = ['+', '-', '*', '/'];

    /**
     * @return list<array{type: string, value: string}>
     */
    public function tokenize(string $formula): array
    {
        $formula = trim($formula);

        if ($formula === '') {
            throw InvalidFormula::empty();
        }

        if (mb_strlen($formula) > self::MAX_LENGTH) {
            throw InvalidFormula::tooLong(self::MAX_LENGTH);
        }

        $tokens = [];
        $length = strlen($formula);
        $position = 0;

        while ($position < $length) {
            $character = $formula[$position];

            if (ctype_space($character)) {
                $position++;

                continue;
            }

            if ($character === '{') {
                $tokens[] = $this->placeholder($formula, $position);

                continue;
            }

            if (in_array($character, self::OPERATORS, true)) {
                $tokens[] = ['type' => 'operator', 'value' => $character];
                $position++;

                continue;
            }

            if ($character === '(' || $character === ')') {
                $tokens[] = ['type' => $character === '(' ? 'open' : 'close', 'value' => $character];
                $position++;

                continue;
            }

            // Un nombre nu reste accepte : `{V:25}` est la forme demandee, mais
            // refuser `25` ferait echouer une formule que tout le monde ecrit.
            if (ctype_digit($character) || $character === '.') {
                $tokens[] = $this->number($formula, $position);

                continue;
            }

            throw InvalidFormula::unexpectedCharacter($character, $position);
        }

        if ($tokens === []) {
            throw InvalidFormula::empty();
        }

        return $tokens;
    }

    /**
     * `{P:nom}` ou `{V:nombre}`.
     *
     * @return array{type: string, value: string}
     */
    private function placeholder(string $formula, int &$position): array
    {
        $end = strpos($formula, '}', $position);

        if ($end === false) {
            throw InvalidFormula::unclosedPlaceholder($position);
        }

        $inner = substr($formula, $position + 1, $end - $position - 1);
        $position = $end + 1;

        if (preg_match('/^P:([a-z][a-z0-9_]*)$/i', $inner, $matches) === 1) {
            return ['type' => 'variable', 'value' => strtolower($matches[1])];
        }

        if (preg_match('/^V:(-?\d+(\.\d+)?)$/', $inner, $matches) === 1) {
            return ['type' => 'number', 'value' => $matches[1]];
        }

        throw InvalidFormula::badPlaceholder($inner);
    }

    /**
     * @return array{type: string, value: string}
     */
    private function number(string $formula, int &$position): array
    {
        $matched = preg_match('/\G\d*(\.\d+)?/', $formula, $matches, 0, $position);

        if ($matched !== 1 || $matches[0] === '' || $matches[0] === '.') {
            throw InvalidFormula::unexpectedCharacter($formula[$position], $position);
        }

        $position += strlen($matches[0]);

        return ['type' => 'number', 'value' => $matches[0]];
    }
}
