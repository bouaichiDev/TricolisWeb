<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Http\Middleware\EnsureProviderPortalContext;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Services\OrganizationMailer;
use App\Modules\ProviderPortal\Mail\ProviderPortalMail;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Envoie le lien de réinitialisation du portail fournisseur.
 *
 * Le broker de mots de passe est celui de l'application ; seul le lien
 * diffère — il mène au portail fournisseur. Rien ne part si l'adresse n'ouvre
 * pas ce portail, et l'appelant répond la même chose dans tous les cas : ce
 * formulaire ne doit pas servir d'annuaire.
 */
final readonly class SendProviderPasswordReset
{
    public function __construct(private OrganizationMailer $mailer) {}

    public function execute(string $email): void
    {
        $user = User::where('email', Str::lower($email))->first();

        if ($user === null || $user->status !== UserStatus::ACTIVE) {
            return;
        }

        $membership = ProviderUser::with('provider')
            ->where('user_id', $user->id)
            ->where('status', UserStatus::ACTIVE->value)
            ->whereHas('provider', static fn ($query) => $query->where('status', EnsureProviderPortalContext::ACTIVE_PROVIDER))
            ->first();

        if ($membership === null) {
            return;
        }

        $token = Password::broker()->createToken($user);
        $url = sprintf('%s/reset-password?token=%s&email=%s', rtrim(Config::string('provider_portal.url'), '/'), $token, urlencode($user->email));

        $this->mailer->for($membership->provider->organization_id)->to($user->email)->send(new ProviderPortalMail(
            ProviderPortalMail::PASSWORD_RESET,
            'Réinitialisation de votre mot de passe',
            $url,
            [
                'firstName' => $user->first_name,
                'expiresInMinutes' => (string) (config('auth.passwords.users.expire') ?? 60),
            ],
        ));
    }
}
