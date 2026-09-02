<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services\Senders;

use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Integrations\Services\OrganizationMailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envoi par e-mail, depuis la boîte de l'organisation.
 *
 * C'est le seul canal réellement acheminé : `MAIL_MAILER=log` en développement,
 * SMTP dès qu'un mailer est configuré. Aucun fournisseur tiers n'est ajouté.
 *
 * **L'organisation part de sa propre boîte** quand elle en a réglé une. Deux
 * transporteurs hébergés sur la même installation ne peuvent pas signer leurs
 * courriers du même nom : le client de l'un recevrait un avis venu de l'autre.
 * Sans réglage, la messagerie du projet prend le relais — activer la
 * fonctionnalité ne doit couper personne.
 *
 * Les pièces jointes ne sont pas attachées au message : le §30 précise « ne pas
 * copier physiquement le fichier sans besoin », et le stockage des documents
 * (`storage_path`) n'est pas garanti local. Elles restent consultables par
 * l'API des documents.
 */
final readonly class EmailCommunicationSender implements CommunicationSender
{
    public function __construct(private OrganizationMailer $mailer) {}

    public function send(OrderCommunication $communication): SenderResult
    {
        if ($communication->recipient_email === null) {
            return SenderResult::failure('Aucune adresse e-mail n’est renseignée pour ce destinataire.');
        }

        $messageId = (string) Str::ulid();

        $replyTo = $this->mailer->replyToFor($communication->organization_id);

        try {
            $this->mailer->for($communication->organization_id)->html(
                $communication->body,
                function (Message $message) use ($communication, $messageId, $replyTo): void {
                    $message->to($communication->recipient_email, $communication->recipient_name)
                        ->subject($communication->subject ?? '');

                    if ($replyTo !== null) {
                        $message->replyTo($replyTo);
                    }

                    $message->getHeaders()->addTextHeader('X-Tricolis-Communication', $messageId);
                },
            );
        } catch (Throwable $exception) {
            return SenderResult::failure('Échec de l’envoi e-mail : '.$exception->getMessage());
        }

        return SenderResult::success($messageId, [
            'channel' => 'email',
            // Quelle boite a servi : sans cette trace, un courrier parti de la
            // mauvaise adresse ne se diagnostique pas apres coup.
            'mailer' => $this->mailer->configurationFor($communication->organization_id)?->from_address
                ?? (string) config('mail.default'),
        ]);
    }
}
