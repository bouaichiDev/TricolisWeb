<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Facades\Crypt;

/**
 * Chiffrement réversible des secrets de transport.
 *
 * Le §20 impose Laravel Encryption et interdit tout mécanisme maison. Le
 * chiffrement doit être **réversible** ici, contrairement aux mots de passe
 * utilisateurs : le transport devra présenter le mot de passe en clair au
 * serveur FTP ou SFTP distant.
 *
 * La clé de chiffrement est celle de l'application (`APP_KEY`) : perdre cette
 * clé rend les mots de passe de transport illisibles — c'est le compromis
 * assumé de `Crypt`, et il vaut mieux que de les stocker en clair.
 */
final readonly class Secret
{
    /**
     * Chiffre une valeur, ou rend `null` telle quelle.
     */
    public static function encrypt(?string $value): ?string
    {
        return $value === null ? null : Crypt::encryptString($value);
    }

    /**
     * Déchiffre une valeur pour un usage immédiat.
     *
     * **Ne jamais restituer le résultat dans une réponse HTTP ni dans un
     * journal.** Le seul appelant légitime est le transport qui doit
     * s'authentifier auprès du serveur distant.
     */
    public static function decrypt(?string $value): ?string
    {
        return $value === null ? null : Crypt::decryptString($value);
    }
}
