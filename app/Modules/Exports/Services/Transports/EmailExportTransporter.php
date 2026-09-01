<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Transports;

use App\Modules\Exports\Mail\InvoiceExportMail;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Envoie la facture par courriel, en pièce jointe.
 *
 * **Les destinataires viennent de `settings`, pas d'une colonne.** Le §31
 * interdit d'ajouter des colonnes propres à un transport, et une adresse de
 * facturation n'est pas un secret — elle a donc sa place dans les réglages
 * non secrets que le §66 autorise.
 *
 * **Aucun destinataire, aucun envoi.** Le §28 tolère un client sans
 * intégration, mais une destination courriel sans adresse est une
 * configuration inachevée : l'envoi échoue en le disant, plutôt que de partir
 * dans le vide et de compter comme transmis.
 *
 * Le corps reste sobre et sans donnée sensible : le fichier joint porte la
 * facture, et un message qui la reprendrait ferait fuiter des montants dans
 * les journaux du serveur de messagerie.
 */
final readonly class EmailExportTransporter implements ExportTransporter
{
    public function send(
        CustomerExportConfiguration $configuration,
        string $fileName,
        string $contents,
        string $contentType,
    ): void {
        $settings = $configuration->settings ?? [];
        $recipients = $this->recipients($settings);

        if ($recipients === []) {
            throw new RuntimeException('Aucun destinataire n’est configuré pour cet envoi par courriel.');
        }

        $subject = is_string($settings['subject'] ?? null) && $settings['subject'] !== ''
            ? $settings['subject']
            : 'Facture '.pathinfo($fileName, PATHINFO_FILENAME);

        Mail::to($recipients)->send(new InvoiceExportMail(
            $subject,
            $this->body($settings),
            $fileName,
            $contents,
            $contentType,
        ));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function recipients(array $settings): array
    {
        $declared = $settings['recipients'] ?? null;

        // Une chaine separee par des virgules autant qu'une liste : les deux
        // formes se saisissent naturellement, et refuser l'une obligerait a
        // deviner laquelle le client a retenue.
        $values = is_string($declared) ? explode(',', $declared) : (is_array($declared) ? $declared : []);

        return array_values(array_filter(
            array_map(static fn ($value): string => is_string($value) ? trim($value) : '', $values),
            static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function body(array $settings): string
    {
        return is_string($settings['body'] ?? null) && $settings['body'] !== ''
            ? $settings['body']
            : 'Veuillez trouver la facture en pièce jointe.';
    }
}
