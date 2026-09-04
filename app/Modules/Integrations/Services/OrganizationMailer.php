<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Models\OrganizationMailConfiguration;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Le mailer d'une organisation, ou celui du projet à défaut.
 *
 * **Chaque organisation part de sa propre boîte.** Deux transporteurs hébergés
 * sur la même installation ne peuvent pas signer leurs courriers du même nom :
 * le client d'Atlas recevrait une facture venue de Tricolis, et se demanderait
 * qui la lui réclame. C'est aussi ce qui permet au domaine du client de publier
 * un SPF valable — un envoi « de » contact@atlas.ch parti d'un serveur qu'atlas
 * n'a pas déclaré finit en indésirable.
 *
 * **Le repli est le mailer du projet**, pas une erreur : une organisation qui
 * n'a pas encore réglé sa boîte doit continuer d'envoyer, sans quoi activer la
 * fonctionnalité couperait les notifications de tout le monde.
 *
 * Le transport est enregistré sous un nom dérivé de l'identifiant de la
 * configuration : deux organisations n'écrasent pas la définition l'une de
 * l'autre au sein d'une même requête.
 */
final readonly class OrganizationMailer
{
    /**
     * Le mailer à utiliser pour cette organisation.
     *
     * Une configuration désactivée compte comme absente : c'est l'intérêt de
     * l'interrupteur, revenir au mailer du projet sans effacer ses réglages.
     */
    public function for(?string $organizationId): Mailer
    {
        $configuration = $this->configurationFor($organizationId);

        if ($configuration === null) {
            return Mail::mailer(Config::string('mail.default'));
        }

        $name = 'organization-'.$configuration->id;

        Config::set("mail.mailers.{$name}", [
            'transport' => 'smtp',
            'host' => $configuration->host,
            'port' => $configuration->port,
            // `scheme` plutôt que `encryption` : Symfony choisit `smtps` pour
            // une connexion chiffrée dès la poignée de main, `smtp` sinon —
            // avec STARTTLS négocié quand le serveur l'annonce.
            'scheme' => $configuration->encryption === 'ssl' ? 'smtps' : 'smtp',
            'username' => $configuration->username,
            'password' => $configuration->password(),
            'timeout' => null,
        ]);

        Config::set('mail.from.address', $configuration->from_address);
        Config::set('mail.from.name', $configuration->from_name ?? $configuration->from_address);

        $configuration->forceFill(['last_used_at' => now()])->saveQuietly();

        return Mail::mailer($name);
    }

    /**
     * Fait de la boîte de l'organisation le mailer par défaut de la requête.
     *
     * Les notifications de Laravel — le lien de réinitialisation, notamment —
     * choisissent leur transport elles-mêmes, par `mail.default` : leur passer
     * un mailer construit ici est impossible sans réécrire la notification.
     *
     * La bascule ne vaut que pour le processus courant, et une requête HTTP ne
     * sert qu'une organisation. Sans elle, un lien de réinitialisation partirait
     * signé du serveur plutôt que du transporteur, et ne serait pas cru.
     */
    public function useFor(?string $organizationId): void
    {
        $configuration = $this->configurationFor($organizationId);

        if ($configuration === null) {
            return;
        }

        $this->for($organizationId);

        Config::set('mail.default', 'organization-'.$configuration->id);
    }

    /** L'adresse de réponse à poser, quand l'organisation en déclare une. */
    public function replyToFor(?string $organizationId): ?string
    {
        return $this->configurationFor($organizationId)?->reply_to;
    }

    public function configurationFor(?string $organizationId): ?OrganizationMailConfiguration
    {
        if ($organizationId === null) {
            return null;
        }

        return OrganizationMailConfiguration::inOrganization($organizationId)
            ->where('is_active', true)
            ->first();
    }
}
