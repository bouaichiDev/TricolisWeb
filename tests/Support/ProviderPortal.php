<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Support\ProviderPortalToken;
use App\Modules\Providers\Models\Provider;

/**
 * Accès aux fournisseurs de démonstration du portail (Transports Alpha et
 * Beta) et à leurs membres, tels que `DemoProviderPortalSeeder` les pose.
 */
final class ProviderPortal
{
    public const string PASSWORD = 'password';

    public static function provider(string $code): Provider
    {
        return Provider::where('code', $code)->firstOrFail();
    }

    public static function member(string $email): ProviderUser
    {
        return ProviderUser::with(['user', 'provider', 'roles'])
            ->whereHas('user', static fn ($query) => $query->where('email', $email))
            ->firstOrFail();
    }

    /**
     * Un jeton de portail, tel que la connexion le délivrerait.
     */
    public static function token(string $email): string
    {
        $membership = self::member($email);

        return $membership->user->createToken(
            ProviderPortalToken::NAME,
            ProviderPortalToken::abilitiesFor($membership->provider_id),
        )->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    public static function headers(string $email): array
    {
        return self::bearer(self::token($email));
    }

    /**
     * @return array<string, string>
     */
    public static function bearer(string $token): array
    {
        // Le garde Sanctum mémorise l'utilisateur d'une requête à l'autre dans
        // un même test : l'oublier évite qu'un jeton hérite du compte précédent.
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.$token];
    }

    public static function internalToken(User $user): string
    {
        return $user->createToken('internal')->plainTextToken;
    }
}
