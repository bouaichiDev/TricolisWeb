<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Services;

use App\Modules\Pricing\Exceptions\InvalidFormula;

/**
 * Construit l'arbre d'une formule, sans jamais l'exécuter.
 *
 * Descente récursive classique : une expression est une suite de termes séparés
 * par `+` et `-`, un terme une suite de facteurs séparés par `*` et `/`. La
 * priorité des opérateurs vient donc de la grammaire, pas d'une astuce de
 * calcul — et elle est la même que celle qu'on écrit à la main.
 *
 * L'arbre ne contient que trois formes de nœuds : un nombre, une variable, une
 * opération binaire. Il n'y a pas de nœud « appel de fonction », donc rien à
 * appeler : c'est ce qui rend le §169G tenable par construction plutôt que par
 * vigilance.
 *
 * L'imbrication est bornée : une formule qui ouvre mille parenthèses ferait
 * exploser la pile de l'évaluateur.
 */
final readonly class FormulaParser
{
    private const int MAX_DEPTH = 20;

    public function __construct(private FormulaTokenizer $tokenizer) {}

    /**
     * @return array<string, mixed> nœud racine
     */
    public function parse(string $formula): array
    {
        $tokens = $this->tokenizer->tokenize($formula);
        $position = 0;

        $node = $this->expression($tokens, $position, 0);

        if ($position < count($tokens)) {
            throw InvalidFormula::syntax(sprintf(
                'élément « %s » en trop',
                $tokens[$position]['value'],
            ));
        }

        return $node;
    }

    /**
     * Les variables que la formule utilise réellement.
     *
     * Sert à l'écran de test — il ne demande une valeur que pour ce qui compte —
     * et à la validation, qui refuse un paramètre inconnu.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public function variables(array $node): array
    {
        if ($node['type'] === 'variable') {
            return [$node['name']];
        }

        if ($node['type'] === 'binary') {
            return array_values(array_unique(array_merge(
                $this->variables($node['left']),
                $this->variables($node['right']),
            )));
        }

        return [];
    }

    /**
     * @param  list<array{type: string, value: string}>  $tokens
     * @return array<string, mixed>
     */
    private function expression(array $tokens, int &$position, int $depth): array
    {
        $node = $this->term($tokens, $position, $depth);

        while (isset($tokens[$position])
            && $tokens[$position]['type'] === 'operator'
            && in_array($tokens[$position]['value'], ['+', '-'], true)) {
            $operator = $tokens[$position]['value'];
            $position++;

            $node = [
                'type' => 'binary',
                'operator' => $operator,
                'left' => $node,
                'right' => $this->term($tokens, $position, $depth),
            ];
        }

        return $node;
    }

    /**
     * @param  list<array{type: string, value: string}>  $tokens
     * @return array<string, mixed>
     */
    private function term(array $tokens, int &$position, int $depth): array
    {
        $node = $this->factor($tokens, $position, $depth);

        while (isset($tokens[$position])
            && $tokens[$position]['type'] === 'operator'
            && in_array($tokens[$position]['value'], ['*', '/'], true)) {
            $operator = $tokens[$position]['value'];
            $position++;

            $node = [
                'type' => 'binary',
                'operator' => $operator,
                'left' => $node,
                'right' => $this->factor($tokens, $position, $depth),
            ];
        }

        return $node;
    }

    /**
     * @param  list<array{type: string, value: string}>  $tokens
     * @return array<string, mixed>
     */
    private function factor(array $tokens, int &$position, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidFormula::tooDeep(self::MAX_DEPTH);
        }

        $token = $tokens[$position] ?? null;

        if ($token === null) {
            throw InvalidFormula::syntax('la formule s’arrête au milieu d’un calcul');
        }

        // Le moins unaire : `-{P:poids}` s'ecrit `0 - poids`, ce qui evite un
        // troisieme type de noeud dans l'arbre.
        if ($token['type'] === 'operator' && $token['value'] === '-') {
            $position++;

            return [
                'type' => 'binary',
                'operator' => '-',
                'left' => ['type' => 'number', 'value' => '0'],
                'right' => $this->factor($tokens, $position, $depth),
            ];
        }

        if ($token['type'] === 'number') {
            $position++;

            return ['type' => 'number', 'value' => $token['value']];
        }

        if ($token['type'] === 'variable') {
            $position++;

            return ['type' => 'variable', 'name' => $token['value']];
        }

        if ($token['type'] === 'open') {
            $position++;
            $node = $this->expression($tokens, $position, $depth + 1);

            if (($tokens[$position]['type'] ?? null) !== 'close') {
                throw InvalidFormula::syntax('parenthèse ouverte et jamais refermée');
            }

            $position++;

            return $node;
        }

        throw InvalidFormula::syntax(sprintf('« %s » n’est pas attendu ici', $token['value']));
    }
}
