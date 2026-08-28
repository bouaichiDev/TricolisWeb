<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Formats;

use App\Modules\Exports\DTOs\InvoiceExportData;
use App\Modules\Exports\Services\ExportFieldMapping;

/**
 * La facture en JSON.
 *
 * Le mapping du client est appliqué avant sérialisation : chaque destinataire
 * nomme les champs à sa façon, et le §65 interdit d'en faire une table de
 * modèles. Il vit dans `settings`, déclaratif, sur une liste blanche.
 *
 * `JSON_UNESCAPED_UNICODE` : sans lui, « Genève » part en `Genève`. C'est
 * du JSON valide, mais illisible dans un fichier déposé sur un FTP.
 */
final readonly class InvoiceJsonFormatter implements InvoiceFormatter
{
    public function __construct(private ExportFieldMapping $mapping) {}

    public function render(InvoiceExportData $invoice, array $settings, string $encoding): string
    {
        $payload = $this->mapping->apply($invoice, $settings);

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return $this->encode($json, $encoding);
    }

    public function extension(): string
    {
        return 'json';
    }

    public function contentType(): string
    {
        return 'application/json';
    }

    /**
     * Convertit vers l'encodage demandé par le client.
     *
     * Le §130 interdit de figer UTF-8 quand le contrat du client dit autre
     * chose. Une conversion impossible rend le texte d'origine plutôt qu'un
     * fichier tronqué : mieux vaut un encodage inattendu qu'un contenu perdu.
     */
    private function encode(string $content, string $encoding): string
    {
        if ($encoding === '' || strcasecmp($encoding, 'UTF-8') === 0) {
            return $content;
        }

        $converted = @mb_convert_encoding($content, $encoding, 'UTF-8');

        return $converted === false ? $content : $converted;
    }
}
