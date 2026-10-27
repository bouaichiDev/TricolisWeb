<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Courriel du portail client : invitation ou réinitialisation.
 *
 * Un seul `Mailable` pour les deux, parce qu'ils ne diffèrent que par la vue
 * et ses variables. Les tests l'interceptent pour relire le lien envoyé : le
 * jeton brut n'existe nulle part ailleurs.
 *
 * @property-read array<string, mixed> $data
 */
final class CustomerPortalMail extends Mailable
{
    public const string INVITATION = 'invitation';

    public const string PASSWORD_RESET = 'password-reset';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $subjectLine,
        public readonly string $actionUrl,
        public readonly array $data = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.customer-portal.'.$this->kind,
            with: [...$this->data, 'actionUrl' => $this->actionUrl],
        );
    }
}
