<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

use App\Modules\Platform\Models\AccessRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * L'avis envoyé à la plateforme quand une demande d'accès arrive.
 *
 * **Le message porte de quoi décider sans ouvrir l'écran** : qui demande, pour
 * quelle société, et par où le rappeler. Un avis qui dirait seulement « une
 * demande est arrivée » obligerait à se connecter pour savoir s'il y a urgence,
 * et c'est ce qui fait qu'on ne les lit plus.
 *
 * En texte brut : il part vers des boîtes que nous ne connaissons pas, et une
 * mise en page HTML n'ajouterait rien à quatre lignes de coordonnées.
 */
final class AccessRequestSubmittedMail extends Mailable
{
    public function __construct(private readonly AccessRequest $accessRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle demande d’accès — '.$this->accessRequest->company_name,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'platform.access-request', with: [
            'request' => $this->accessRequest,
        ]);
    }
}
