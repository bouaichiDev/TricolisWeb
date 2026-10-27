<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Support;

use DateTimeInterface;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Ce qui distingue un jeton du portail fournisseur des autres jetons.
 *
 * Un même `User` peut être collaborateur d'un transporteur, membre du portail
 * d'un client **et** membre du portail d'un fournisseur. Les jetons vivent
 * dans la même table ; seules leurs capacités les séparent :
 *
 * - jeton interne : `['*']` ;
 * - jeton portail client : `['customer-portal', 'customer:<id>']` ;
 * - jeton portail fournisseur : `['provider-portal', 'provider:<id>']`.
 *
 * Le fournisseur courant est **gravé dans le jeton** à la connexion ; il ne se
 * lit jamais dans un en-tête ou un paramètre. `can()` n'est pas utilisé : il
 * répond oui à tout pour un jeton `*`.
 */
final class ProviderPortalToken
{
    public const string ABILITY = 'provider-portal';

    public const string PROVIDER_PREFIX = 'provider:';

    public const string NAME = 'provider-portal';

    /**
     * @return array<int, string>
     */
    public static function abilitiesFor(string $providerId): array
    {
        return [self::ABILITY, self::PROVIDER_PREFIX.$providerId];
    }

    /**
     * L'échéance d'un jeton émis maintenant (`provider_portal.token_ttl_minutes`).
     */
    public static function expiresAt(): ?DateTimeInterface
    {
        $minutes = (int) config('provider_portal.token_ttl_minutes', 720);

        return $minutes > 0 ? now()->addMinutes($minutes) : null;
    }

    public static function isPortalToken(mixed $token): bool
    {
        return $token instanceof PersonalAccessToken
            && in_array(self::ABILITY, (array) $token->abilities, true);
    }

    public static function providerIdOf(mixed $token): ?string
    {
        if (! self::isPortalToken($token)) {
            return null;
        }

        foreach ((array) $token->abilities as $ability) {
            if (is_string($ability) && str_starts_with($ability, self::PROVIDER_PREFIX)) {
                return substr($ability, strlen(self::PROVIDER_PREFIX));
            }
        }

        return null;
    }
}
