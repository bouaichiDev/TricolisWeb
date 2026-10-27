<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Documents;

use App\Modules\Documents\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Présenter et servir un document au portail — jamais son chemin de stockage.
 *
 * Le fichier ne quitte le disque privé que par ce téléchargement, **après**
 * le contrôle d'accès de l'appelant. Le chemin vient de la base, jamais de la
 * requête ; un chemin qui prétendrait remonter l'arborescence est refusé.
 * Fichier manquant : 404 contrôlé plutôt qu'une erreur de flux.
 */
final class CustomerDocumentFiles
{
    /** Le type que `GenerateDeliveryNoteAction` donne aux bons de livraison. */
    public const string DELIVERY_NOTE = 'delivery_note';

    /**
     * @return array<string, mixed>
     */
    public static function present(Document $document): array
    {
        return [
            'id' => $document->id,
            'fileName' => $document->file_name,
            'documentType' => $document->document_type,
            'isDeliveryNote' => $document->document_type === self::DELIVERY_NOTE,
            'referenceNumber' => $document->reference_number,
            'mimeType' => $document->mime_type,
            'size' => $document->size,
            'createdAt' => $document->created_at?->toIso8601String(),
        ];
    }

    public static function download(Document $document): StreamedResponse
    {
        $path = (string) $document->storage_path;
        $disk = Storage::disk('local');

        abort_if($path === '' || str_contains($path, '..') || ! $disk->exists($path), 404, 'Ce fichier n’est plus disponible.');

        return $disk->download($path, self::safeName($document), [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Nom proposé au navigateur : ni séparateur de chemin, ni caractère de contrôle. */
    private static function safeName(Document $document): string
    {
        $name = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', (string) $document->file_name);

        return Str::limit($name !== '' ? $name : 'document', 180, '');
    }
}
