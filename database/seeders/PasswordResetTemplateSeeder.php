<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Identity\Services\SendPasswordResetLink;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Templates\Enums\TemplateType;
use App\Modules\Templates\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Le courriel de reinitialisation, offert a la modification.
 *
 * **Un modele vide ne se modifie pas.** Demander a un administrateur d'ecrire
 * de zero le courriel qui rend l'acces a ses employes, avec les bons
 * placeholders et sans se tromper de nom de variable, c'est lui garantir un
 * texte casse ou pas de texte du tout. Le semis pose une version correcte ;
 * l'administrateur la retouche.
 *
 * **Rejouable.** Le code fait la cle : un second passage ne recree rien, et ne
 * reecrit surtout pas un texte que quelqu'un a deja adapte.
 */
class PasswordResetTemplateSeeder extends Seeder
{
    public const string CODE = 'PASSWORD_RESET';

    public function run(): void
    {
        foreach (Organization::cursor() as $organization) {
            Template::firstOrCreate(
                ['organization_id' => $organization->id, 'code' => self::CODE],
                [
                    'name' => 'Réinitialisation de mot de passe',
                    'channel' => CommunicationChannel::EMAIL->value,
                    'template_type' => TemplateType::PASSWORD_RESET->value,
                    'subject_template' => 'Réinitialisation de votre mot de passe',
                    'body_template' => $this->body(),
                    'language' => 'fr',
                    // Declarees, sans quoi le rendu refuse les placeholders du
                    // corps : c'est la liste que l'ecran propose a l'edition.
                    'available_variables' => SendPasswordResetLink::availableVariables(),
                    'is_default' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Le corps par defaut.
     *
     * Sobre et sans mise en page savante : il sera relu et modifie dans un
     * champ de texte, pas dans un editeur graphique. Le lien apparait en clair
     * sous le bouton — certains clients de messagerie n'affichent pas les liens
     * stylises, et un courriel qu'on ne peut pas suivre ne sert a rien.
     */
    private function body(): string
    {
        return <<<'HTML'
            <p>Bonjour {{ user.firstName }},</p>

            <p>
              Une réinitialisation de mot de passe a été demandée pour votre compte
              {{ user.email }} chez {{ organization.name }}.
            </p>

            <p><a href="{{ resetUrl }}">Choisir un nouveau mot de passe</a></p>

            <p>Ce lien expire dans {{ expiresInMinutes }} minutes.</p>

            <p>
              Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer ce
              message : votre mot de passe actuel reste valable.
            </p>

            <p>
              Si le lien ne s'ouvre pas, copiez cette adresse dans votre navigateur :<br>
              {{ resetUrl }}
            </p>

            <p>{{ organization.name }}</p>
            HTML;
    }
}
