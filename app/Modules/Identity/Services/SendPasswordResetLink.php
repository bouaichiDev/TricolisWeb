<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Mail\PasswordResetMail;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Services\OrganizationMailer;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Templates\Enums\TemplateType;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\TemplateRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Envoie a un membre le lien qui lui rendra son acces.
 *
 * **Le texte appartient a l'organisation.** Un courriel signe « Laravel » dans
 * la boite d'un chauffeur ne rassure personne, et l'administrateur ne peut pas
 * le corriger : le modele `password_reset` lui rend la main sur l'objet et le
 * corps, comme pour tous les autres courriels qu'il envoie.
 *
 * **Le lien part quand meme si le modele est casse.** Un placeholder mal
 * ecrit, un modele desactive, une variable non declaree : dans tous ces cas la
 * notification de Laravel prend le relais. Quelqu'un est bloque dehors ; lui
 * refuser son lien parce qu'un texte est fautif serait la mauvaise priorite.
 */
final readonly class SendPasswordResetLink
{
    /** Duree de validite, telle que le broker la regle. */
    private const int DEFAULT_EXPIRY_MINUTES = 60;

    public function __construct(
        private TemplateRenderer $renderer,
        private OrganizationMailer $mailer,
        private PasswordResetUrl $url,
    ) {}

    public function execute(User $user, string $organizationId): void
    {
        $token = Password::broker()->createToken($user);
        $template = $this->templateFor($organizationId);

        if ($template === null || ! $this->sendFromTemplate($user, $organizationId, $template, $token)) {
            // Le repli : `createUrlUsing` lui a deja donne la bonne adresse.
            $user->sendPasswordResetNotification($token);
        }
    }

    private function templateFor(string $organizationId): ?Template
    {
        return Template::where('organization_id', $organizationId)
            ->where('template_type', TemplateType::PASSWORD_RESET->value)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();
    }

    /** Faux quand le modele n'a pas pu etre rendu ou envoye. */
    private function sendFromTemplate(
        User $user,
        string $organizationId,
        Template $template,
        string $token,
    ): bool {
        try {
            $rendered = $this->renderer->render($template, $this->variables($user, $organizationId, $token));

            $this->mailer->for($organizationId)->to($user->email)->send(new PasswordResetMail(
                $rendered->subject ?? 'Réinitialisation de votre mot de passe',
                $rendered->body,
            ));

            return true;
        } catch (Throwable $exception) {
            // Journalise sans le jeton : un lien valable une heure n'a rien a
            // faire dans un fichier de journal.
            Log::warning('Modèle de réinitialisation inutilisable, envoi par défaut', [
                'template' => $template->code,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function variables(User $user, string $organizationId, string $token): array
    {
        return [
            'user' => [
                'firstName' => (string) $user->first_name,
                'lastName' => (string) $user->last_name,
                'email' => (string) $user->email,
            ],
            'organization' => [
                'name' => (string) (Organization::whereKey($organizationId)->value('name') ?? ''),
            ],
            'resetUrl' => $this->url->for($user, $token),
            'expiresInMinutes' => (string) (
                config('auth.passwords.users.expire') ?? self::DEFAULT_EXPIRY_MINUTES
            ),
        ];
    }

    /**
     * Les variables qu'un modele de reinitialisation peut employer.
     *
     * Exposees pour que le semis les declare et que l'ecran les propose : un
     * placeholder non declare fait echouer le rendu, et l'administrateur n'a
     * aucun moyen de deviner la liste.
     *
     * @return list<string>
     */
    public static function availableVariables(): array
    {
        return [
            'user.firstName',
            'user.lastName',
            'user.email',
            'organization.name',
            'resetUrl',
            'expiresInMinutes',
        ];
    }
}
