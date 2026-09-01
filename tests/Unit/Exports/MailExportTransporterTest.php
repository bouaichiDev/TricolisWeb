<?php

use App\Modules\Exports\Mail\InvoiceExportMail;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Services\Transports\EmailExportTransporter;
use App\Modules\Exports\Services\Transports\ManualExportTransporter;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Les deux transports qui ne joignent aucun serveur du client.
 *
 * Le courriel passe par notre propre relais ; le mode manuel ne passe nulle
 * part. Aucun message ne sort réellement d'ici : le facteur est doublé, et ce
 * qu'on vérifie est le message tel qu'il aurait été remis.
 */
uses(TestCase::class);

beforeEach(function (): void {
    $this->email = new EmailExportTransporter;
    $this->manual = new ManualExportTransporter;

    $this->configuration = fn (array $settings): CustomerExportConfiguration => new CustomerExportConfiguration([
        'transport' => 'email',
        'settings' => $settings,
    ]);

    $this->send = function (array $settings): void {
        $this->email->send(
            ($this->configuration)($settings),
            'INV-2026-00042.pdf',
            '%PDF-1.4',
            'application/pdf',
        );
    };
});

it('remet la facture en pièce jointe aux destinataires', function (): void {
    Mail::fake();

    ($this->send)(['recipients' => 'compta@client.example, factures@client.example']);

    Mail::assertSent(InvoiceExportMail::class, function (InvoiceExportMail $mailable): bool {
        return $mailable->hasTo('compta@client.example')
            && $mailable->hasTo('factures@client.example')
            && count($mailable->attachments()) === 1;
    });
});

it('accepte une liste autant qu’une chaîne', function (): void {
    Mail::fake();

    ($this->send)(['recipients' => ['compta@client.example']]);

    Mail::assertSentCount(1);
});

/**
 * Une destination sans adresse est une configuration inachevée : elle doit le
 * dire, pas partir dans le vide et compter comme transmise.
 */
it('refuse d’envoyer sans destinataire', function (): void {
    Mail::fake();

    expect(fn () => ($this->send)([]))->toThrow(RuntimeException::class, 'destinataire');

    Mail::assertNothingSent();
});

it('ignore une adresse manifestement invalide', function (): void {
    Mail::fake();

    expect(fn () => ($this->send)(['recipients' => 'pas-une-adresse']))
        ->toThrow(RuntimeException::class, 'destinataire');
});

it('reprend l’objet et le corps configurés', function (): void {
    Mail::fake();

    ($this->send)([
        'recipients' => 'compta@client.example',
        'subject' => 'Votre facture d’août',
        'body' => 'Bonjour, voici la facture du mois.',
    ]);

    Mail::assertSent(InvoiceExportMail::class, function (InvoiceExportMail $mailable): bool {
        $content = $mailable->content();

        return $mailable->envelope()->subject === 'Votre facture d’août'
            && $content instanceof Content
            && str_contains((string) $content->with['text'], 'voici la facture du mois');
    });
});

/** Sans objet configuré, le nom du fichier reste le repère le plus parlant. */
it('titre le message d’après la facture à défaut d’objet', function (): void {
    Mail::fake();

    ($this->send)(['recipients' => 'compta@client.example']);

    Mail::assertSent(
        InvoiceExportMail::class,
        fn (InvoiceExportMail $mailable): bool => $mailable->envelope()->subject === 'Facture INV-2026-00042',
    );
});

/**
 * Le mode manuel est un transport à part entière : le fichier est produit et
 * rangé par le répartiteur, et personne n'est appelé.
 */
it('ne transmet rien en mode manuel, sans échouer pour autant', function (): void {
    Mail::fake();

    $this->manual->send(
        new CustomerExportConfiguration(['transport' => 'manual']),
        'INV-2026-00042.pdf',
        '%PDF-1.4',
        'application/pdf',
    );

    Mail::assertNothingSent();
});
