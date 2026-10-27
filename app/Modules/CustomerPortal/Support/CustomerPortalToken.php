<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Support;

use DateTimeInterface;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Ce qui distingue un jeton du portail client d'un jeton interne.
 *
 * Un même `User` peut être collaborateur d'un transporteur **et** membre du
 * portail d'un client. Les deux jetons vivent donc dans la même table ; seules
 * leurs capacités les séparent :
 *
 * - jeton interne : `['*']` ;
 * - jeton portail : `['customer-portal', 'customer:<id>']`.
 *
 * Le Customer courant est **gravé dans le jeton** à la connexion. Il ne se lit
 * jamais dans un en-tête ou un paramètre : changer d'identifiant dans une
 * requête ne change pas de client.
 *
 * `can()` n'est pas utilisé : il répond oui à tout pour un jeton `*`, ce qui
 * laisserait un jeton interne entrer dans le portail.
 */
final class CustomerPortalToken
{
    public const string ABILITY = 'customer-portal';

    public const string CUSTOMER_PREFIX = 'customer:';

    public const string NAME = 'customer-portal';

    /**
     * @return array<int, string>
     */
    public static function abilitiesFor(string $customerId): array
    {
        return [self::ABILITY, self::CUSTOMER_PREFIX.$customerId];
    }

    /**
     * L'échéance d'un jeton émis maintenant (`customer_portal.token_ttl_minutes`).
     * Zéro ou moins : pas d'échéance — à n'employer qu'en connaissance de cause.
     */
    public static function expiresAt(): ?DateTimeInterface
    {
        $minutes = (int) config('customer_portal.token_ttl_minutes', 720);

        return $minutes > 0 ? now()->addMinutes($minutes) : null;
    }

    public static function isPortalToken(mixed $token): bool
    {
        return $token instanceof PersonalAccessToken
            && in_array(self::ABILITY, (array) $token->abilities, true);
    }

    public static function customerIdOf(mixed $token): ?string
    {
        if (! self::isPortalToken($token)) {
            return null;
        }

        foreach ((array) $token->abilities as $ability) {
            if (is_string($ability) && str_starts_with($ability, self::CUSTOMER_PREFIX)) {
                return substr($ability, strlen(self::CUSTOMER_PREFIX));
            }
        }

        return null;
    }
}
