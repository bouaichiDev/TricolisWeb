<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Exceptions;

use RuntimeException;

/**
 * Une formule qu'on refuse de lire.
 *
 * Chaque cas porte son message : le §169I demande une erreur métier claire
 * plutôt qu'un tarif approximatif. Celui qui compose un tarif doit savoir ce
 * qui cloche, pas découvrir un prix faux trois semaines plus tard.
 */
final class InvalidFormula extends RuntimeException
{
    public static function empty(): self
    {
        return new self('La formule est vide.');
    }

    public static function tooLong(int $maximum): self
    {
        return new self("La formule dépasse {$maximum} caractères.");
    }

    public static function unexpectedCharacter(string $character, int $position): self
    {
        return new self("Caractère « {$character} » inattendu en position {$position}.");
    }

    public static function unclosedPlaceholder(int $position): self
    {
        return new self("Accolade ouverte en position {$position} et jamais refermée.");
    }

    public static function badPlaceholder(string $inner): self
    {
        return new self("« {{$inner}} » n’est ni un paramètre {P:nom} ni une valeur {V:nombre}.");
    }

    public static function unknownVariable(string $name, array $allowed): self
    {
        return new self(sprintf(
            'Le paramètre « %s » n’existe pas. Disponibles : %s.',
            $name,
            implode(', ', $allowed),
        ));
    }

    public static function syntax(string $detail): self
    {
        return new self("Formule mal formée : {$detail}.");
    }

    public static function tooDeep(int $maximum): self
    {
        return new self("La formule imbrique plus de {$maximum} niveaux de parenthèses.");
    }

    public static function divisionByZero(): self
    {
        return new self('La formule divise par zéro.');
    }

    public static function missingValue(string $name): self
    {
        return new self("Le paramètre « {$name} » n’a pas de valeur pour cette prestation.");
    }

    public static function outOfRange(): self
    {
        return new self('Le résultat de la formule sort des montants représentables.');
    }
}
