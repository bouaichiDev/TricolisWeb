<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Arithmétique monétaire en précision arbitraire.
 *
 * Le §32 interdit `float` et `double` pour l'argent : `0.1 + 0.2` ne vaut pas
 * `0.3` en binaire, et une facture fausse d'un centime est une facture fausse.
 * Tout passe donc par `bcmath`, sur des chaînes.
 *
 * `bcmath` **tronque** au lieu d'arrondir. La méthode `round()` rétablit
 * l'arrondi commercial — au demi supérieur en valeur absolue — en ajoutant un
 * demi-quantum avant la troncature.
 */
final readonly class Money
{
    /** Échelle interne des calculs, avant arrondi final. */
    private const int SCALE = 10;

    public const int MONEY_SCALE = 2;

    /**
     * Arrondi commercial : 2,345 → 2,35 ; −2,345 → −2,35.
     */
    public static function round(string $value, int $scale = self::MONEY_SCALE): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return bccomp($value, '0', self::SCALE) >= 0
            ? bcadd($value, $half, $scale)
            : bcsub($value, $half, $scale);
    }

    public static function multiply(string $a, string $b): string
    {
        return bcmul($a, $b, self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function subtract(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    /**
     * Applique un pourcentage : `percentOf('100', '20')` vaut `'20'`.
     */
    public static function percentOf(string $base, string $rate): string
    {
        return bcdiv(bcmul($base, $rate, self::SCALE), '100', self::SCALE);
    }

    /**
     * Somme arrondie d'une liste de montants déjà arrondis.
     *
     * @param  iterable<string|float|int|null>  $amounts
     */
    public static function sum(iterable $amounts): string
    {
        $total = '0';

        foreach ($amounts as $amount) {
            $total = self::add($total, (string) ($amount ?? '0'));
        }

        return self::round($total);
    }
}
