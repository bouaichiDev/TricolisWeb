<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services\Senders;

use App\Modules\Communications\Models\OrderCommunication;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envoi par e-mail, via le mailer configuré du projet.
 *
 * C'est le seul canal réellement acheminé : `MAIL_MAILER=log` en développement,
 * SMTP dès qu'un mailer est configuré. Aucun fournisseur tiers n'est ajouté.
 *
 * Les pièces jointes ne sont pas attachées au message : le §30 précise « ne pas
 * copier physiquement le fichier sans besoin », et le stockage des documents
 * (`storage_path`) n'est pas garanti local. Elles restent consultables par
 * l'API des documents.
 */
final readonly class EmailCommunicationSender implements CommunicationSender
{
    public function send(OrderCommunication $communication): SenderResult
    {
        if ($communication->recipient_email === null) {
            return SenderResult::failure('Aucune adresse e-mail n’est renseignée pour ce destinataire.');
        }

        $messageId = (string) Str::ulid();

        try {
            Mail::html($communication->body, function (Message $message) use ($communication, $messageId): void {
                $message->to($communication->recipient_email, $communication->recipient_name)
                    ->subject($communication->subject ?? '')
                    ->getHeaders()
                    ->addTextHeader('X-Tricolis-Communication', $messageId);
            });
        } catch (Throwable $exception) {
            return SenderResult::failure('Échec de l’envoi e-mail : '.$exception->getMessage());
        }

        return SenderResult::success($messageId, [
            'channel' => 'email',
            'mailer' => (string) config('mail.default'),
        ]);
    }
}
