<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Http\Resources\Api\V1\Documents\DocumentCompactResource;
use App\Modules\Communications\Models\CommunicationAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pièce jointe d'une communication.
 *
 * Les deux snapshots sont retournés tels qu'ils ont été figés : si le document
 * a été renommé depuis, `document.fileName` et `fileNameSnapshot` diffèrent —
 * et c'est le snapshot qui décrit ce qui est parti.
 *
 * @mixin CommunicationAttachment
 */
class CommunicationAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'communicationId' => $this->communication_id,
            'documentId' => $this->document_id,
            'fileNameSnapshot' => $this->file_name_snapshot,
            'mimeTypeSnapshot' => $this->mime_type_snapshot,
            'document' => new DocumentCompactResource($this->whenLoaded('document')),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
