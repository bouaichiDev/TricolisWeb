<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Mail\CustomerPortalMail;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Services\OrganizationMailer;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Envoie le lien de réinitialisation du portail client.
 *
 * Le broker de mots de passe est celui de l'application : un seul jeton par
 * compte, une seule table. Seul le lien diffère — il mène au portail, pas à
 * l'administration interne.
 *
 * Rien ne part si l'adresse n'ouvre pas le portail (compte inconnu, inactif,
 * sans appartenance active). L'appelant répond la même chose dans tous les
 * cas : ce formulaire ne doit pas servir d'annuaire.
 */
final readonly class SendCustomerPasswordReset
{
    public function __construct(private OrganizationMailer $mailer) {}

    public function execute(string $email): void
    {
        $user = User::where('email', Str::lower($email))->first();

        if ($user === null || $user->status !== UserStatus::ACTIVE) {
            return;
        }

        $membership = CustomerUser::with('customer')
            ->where('user_id', $user->id)
            ->where('status', UserStatus::ACTIVE->value)
            ->whereHas('customer', static fn ($query) => $query->where('status', CustomerStatus::ACTIVE->value))
            ->first();

        if ($membership === null) {
            return;
        }

        $token = Password::broker()->createToken($user);
        $url = sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim(Config::string('customer_portal.url'), '/'),
            $token,
            urlencode($user->email),
        );

        $this->mailer->for($membership->customer->organization_id)->to($user->email)->send(new CustomerPortalMail(
            CustomerPortalMail::PASSWORD_RESET,
            'Réinitialisation de votre mot de passe',
            $url,
            [
                'firstName' => $user->first_name,
                'expiresInMinutes' => (string) (config('auth.passwords.users.expire') ?? 60),
            ],
        ));
    }
}
