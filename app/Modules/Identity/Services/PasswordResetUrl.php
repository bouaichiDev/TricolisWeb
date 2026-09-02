<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Config;

/**
 * L'adresse ou l'on choisit son nouveau mot de passe.
 *
 * **Elle mene a l'interface, pas a l'API.** La route `auth.password.reset` est
 * un `POST` : elle n'affiche rien, et un lien recu par courriel s'ouvre dans un
 * navigateur. C'est aussi pourquoi le nom `password.reset` que cherchait la
 * notification de Laravel n'existait pas — le corriger en renommant la route
 * n'aurait donne qu'une page blanche.
 *
 * Une seule definition, partagee : la notification par defaut et le courriel
 * construit depuis un modele doivent mener au meme endroit, sinon l'un des deux
 * chemins se casse en silence le jour ou l'adresse change.
 */
final readonly class PasswordResetUrl
{
    public function for(CanResetPassword $notifiable, string $token): string
    {
        return sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim(Config::string('app.frontend_url'), '/'),
            $token,
            urlencode((string) $notifiable->getEmailForPasswordReset()),
        );
    }
}
