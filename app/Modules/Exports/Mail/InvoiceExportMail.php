<?php

declare(strict_types=1);

namespace App\Modules\Exports\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Le message qui porte la facture chez le client.
 *
 * **Le corps est en texte brut, et volontairement pauvre.** Le fichier joint
 * porte la facture ; un message qui reprendrait les montants les ferait passer
 * en clair dans les journaux de chaque relais traversé — le nôtre, celui du
 * client, et ceux du chemin.
 *
 * Objet et corps viennent des réglages de la destination : un service
 * comptable trie sur l'objet, et lui imposer le nôtre l'obligerait à défaire ce
 * tri à la main.
 */
final class InvoiceExportMail extends Mailable
{
    public function __construct(
        private readonly string $subjectLine,
        private readonly string $bodyText,
        private readonly string $fileName,
        private readonly string $contents,
        private readonly string $contentType,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(text: 'exports.mail', with: ['text' => $this->bodyText]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->contents, $this->fileName)
                ->withMime($this->contentType),
        ];
    }
}
