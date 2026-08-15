<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Documents;

use App\Modules\Documents\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Document réduit à ce qui permet de l'identifier et de le télécharger.
 *
 * `storage_path` n'y figure pas : le chemin de stockage est une donnée interne,
 * le téléchargement passe par le module Documents.
 *
 * @mixin Document
 */
class DocumentCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referenceNumber' => $this->reference_number,
            'documentType' => $this->document_type,
            'fileName' => $this->file_name,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
        ];
    }
}
