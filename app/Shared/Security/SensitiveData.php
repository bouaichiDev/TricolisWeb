<?php

declare(strict_types=1);

namespace App\Shared\Security;

/**
 * Le seul endroit qui décide qu'une donnée est un secret, pour l'audit comme
 * pour les journaux applicatifs (Phase 7 portail, §24 et §26).
 *
 * La comparaison porte sur le **nom normalisé** de la clé — minuscules, sans
 * séparateur — si bien que `apiKeyHash`, `api_key_hash` et `API-KEY` sont
 * reconnus de la même façon. Deux familles :
 *
 * - un fragment suffit (`password`, `secret`, `apikey`, `credential`,
 *   `authorization`, `cookie`, `privatekey`, `encrypted`) : rien de ce qui
 *   contient ces mots n'est utile en clair dans une trace ;
 * - `token` seulement en fin de nom (`token`, `accessToken`, `rememberToken`…)
 *   pour ne pas masquer une adresse comme `tokenUrl`, qui n'est pas secrète.
 *
 * La valeur est remplacée par un marqueur, jamais tronquée ni hachée : un
 * hachage de secret court se retrouve par force brute.
 */
final class SensitiveData
{
    public const string REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const array FRAGMENTS = ['password', 'passwd', 'secret', 'apikey', 'credential', 'authorization', 'cookie', 'privatekey', 'encrypted'];

    public static function isSensitiveKey(int|string $key): bool
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower((string) $key)) ?? '';

        if ($normalized === '') {
            return false;
        }

        foreach (self::FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return str_ends_with($normalized, 'token');
    }

    /**
     * Le tableau, chaque valeur sensible remplacée — à toute profondeur.
     *
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    public static function redact(array $values, int $depth = 0): array
    {
        if ($depth > 10) {
            return [self::REDACTED];
        }

        foreach ($values as $key => $value) {
            if (self::isSensitiveKey($key)) {
                $values[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $values[$key] = self::redact($value, $depth + 1);
            }
        }

        return $values;
    }
}
