<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use Illuminate\Support\Str;

/**
 * Génère et empreinte les clés API clients.
 *
 * **Le choix du hash.** Le §12 demande « un mécanisme adapté aux vérifications
 * de clé API ». Bcrypt ne l'est pas : délibérément lent (~100 ms), il coûterait
 * plus cher que la requête qu'il protège, et n'étant pas déterministe, il
 * imposerait de comparer la clé présentée à *toutes* celles du client.
 *
 * SHA-256 est retenu. Un hash rapide est cryptographiquement suffisant **parce
 * que la clé est générée, pas choisie** : 64 caractères issus de `Str::random()`
 * portent bien trop d'entropie pour une attaque par force brute ou par
 * dictionnaire — contrairement à un mot de passe humain, qui justifie bcrypt.
 *
 * C'est le raisonnement de Laravel Sanctum, déjà utilisé dans ce projet, et la
 * convention `hash('sha256', …)` y existe déjà (`LoginUser`, `email_hash`).
 *
 * La clé en clair ne quitte jamais cette classe autrement que par retour de
 * `generate()` : elle n'est ni stockée, ni journalisée, ni auditée.
 */
final readonly class ApiKeyGenerator
{
    /** Longueur de la clé en clair, en caractères. */
    private const int LENGTH = 64;

    /**
     * Produit une clé et son empreinte.
     *
     * @return array{key: string, hash: string} la clé claire n'est retournée
     *                                          qu'ici, et une seule fois
     */
    public function generate(): array
    {
        $key = Str::random(self::LENGTH);

        return ['key' => $key, 'hash' => $this->hash($key)];
    }

    /**
     * Empreinte d'une clé présentée, pour recherche par index unique.
     */
    public function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
