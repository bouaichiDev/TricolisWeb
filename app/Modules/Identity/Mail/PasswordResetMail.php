<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Le courriel de reinitialisation, tel que l'organisation l'a ecrit.
 *
 * Un `Mailable` plutot qu'un envoi brut : c'est ce qui le rend verifiable — un
 * test peut affirmer qu'il est parti, avec quel objet et quel corps — et ce qui
 * le fait ressembler aux autres courriels du projet.
 *
 * Le corps arrive **deja rendu** par le moteur de modeles : la vue ne fait que
 * le porter. La mise en page appartient a l'administrateur, pas au code.
 */
final class PasswordResetMail extends Mailable
{
    public function __construct(
        private readonly string $subjectLine,
        private readonly string $bodyHtml,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->bodyHtml);
    }
}
