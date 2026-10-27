<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\CustomerPortal\Mail\CustomerPortalMail;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Support\CustomerPortalToken;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Accès aux clients de démonstration du portail (IKEA, QOQA) et à leurs
 * membres, tels que `DemoCustomerPortalSeeder` les pose.
 */
final class CustomerPortal
{
    public const string PASSWORD = 'password';

    public static function customer(string $code): Customer
    {
        return Customer::where('code', $code)->firstOrFail();
    }

    public static function member(string $email): CustomerUser
    {
        return CustomerUser::with(['user', 'customer', 'roles'])
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
            CustomerPortalToken::NAME,
            CustomerPortalToken::abilitiesFor($membership->customer_id),
        )->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    public static function headers(string $email): array
    {
        // Le garde Sanctum mémorise l'utilisateur d'une requête à l'autre dans
        // un même test : l'oublier évite qu'un jeton hérite du compte précédent.
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.self::token($email)];
    }

    /**
     * @return array<string, string>
     */
    public static function bearer(string $token): array
    {
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.$token];
    }

    public static function internalToken(User $user): string
    {
        return $user->createToken('internal')->plainTextToken;
    }

    /**
     * Le jeton brut contenu dans le dernier courriel du portail envoyé à cette adresse.
     */
    public static function mailedToken(string $email, string $kind = CustomerPortalMail::INVITATION): string
    {
        $mail = Mail::sent(CustomerPortalMail::class, static fn (CustomerPortalMail $mail): bool => $mail->hasTo($email) && $mail->kind === $kind)->last();

        if ($mail === null) {
            throw new RuntimeException("Aucun courriel « {$kind} » envoyé à {$email}.");
        }

        parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);

        return (string) $query['token'];
    }
}
