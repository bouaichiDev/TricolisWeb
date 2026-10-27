<?php

declare(strict_types=1);

namespace App\Shared\Security;

/**
 * Neutralise une cellule qu'un tableur prendrait pour une formule (Phase 7,
 * §17 — « injection CSV »).
 *
 * Une valeur venue d'un client (désignation importée, référence) peut
 * commencer par `=`, `+`, `-`, `@`, une tabulation ou un retour chariot :
 * ouverte dans Excel, elle s'exécuterait (`=HYPERLINK(…)`, `=cmd|…`). Elle
 * est alors préfixée d'une apostrophe, qui la fait lire comme texte.
 *
 * Un nombre reste un nombre : `-12.5` n'est pas une formule et n'est pas
 * touché — les montants négatifs d'une facture gardent leur forme.
 */
final class SpreadsheetSafe
{
    private const array TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(mixed $value): string
    {
        $text = (string) $value;

        if ($text === '' || is_numeric($text) || ! in_array($text[0], self::TRIGGERS, true)) {
            return $text;
        }

        return "'".$text;
    }
}
