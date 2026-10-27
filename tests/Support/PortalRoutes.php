<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Support\PortalRouteSecurity;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Les routes du portail, lues dans le routeur réel : un test de sécurité
 * piloté par elles couvre d'office toute route ajoutée demain.
 */
final class PortalRoutes
{
    /** Un identifiant bien formé qui ne désigne rien. */
    public const string NOWHERE = '01JZZZZZZZZZZZZZZZZZZZZZZZ';

    /**
     * Les routes authentifiées du portail (jeton Sanctum + contexte client).
     *
     * @return list<array{method: string, uri: string, permission: list<string>}>
     */
    public static function authenticated(): array
    {
        return array_values(array_map(
            static fn (array $row): array => [
                'method' => explode('|', $row['method'])[0],
                'uri' => $row['uri'],
                'permission' => $row['permission'],
            ],
            array_filter(
                app(PortalRouteSecurity::class)->rows(),
                static fn (array $row): bool => $row['context'] === 'customer.portal',
            ),
        ));
    }

    /**
     * L'URI avec ses paramètres remplacés : par la valeur fournie, sinon par
     * un identifiant qui ne désigne rien.
     *
     * @param  array<string, string>  $values
     */
    public static function fill(string $uri, array $values = []): string
    {
        return '/'.preg_replace_callback(
            '/\{(\w+)\??\}/',
            static fn (array $match): string => $values[$match[1]] ?? self::NOWHERE,
            $uri,
        );
    }

    public static function hasParameters(string $uri): bool
    {
        return str_contains($uri, '{');
    }

    /**
     * Un membre actif d'un client, **sans aucun rôle** : zéro permission.
     */
    public static function memberWithoutRoles(string $customerCode): string
    {
        $email = 'sans-role-'.Str::lower(Str::random(6)).'@'.$customerCode.'.test';
        $user = User::create([
            'first_name' => 'Sans', 'last_name' => 'Rôle', 'email' => $email,
            'password' => Hash::make(CustomerPortal::PASSWORD), 'status' => UserStatus::ACTIVE,
        ]);

        CustomerUser::create([
            'customer_id' => CustomerPortal::customer($customerCode)->id,
            'user_id' => $user->id,
            'status' => UserStatus::ACTIVE,
            'activated_at' => now(),
        ]);

        return $email;
    }
}
