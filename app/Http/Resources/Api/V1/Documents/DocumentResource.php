<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Documents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'referenceNumber' => $this->reference_number,
            'documentType' => $this->document_type,
            'status' => $this->status,
            'fileName' => $this->file_name,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'receivedAt' => $this->received_at,
            'createdBy' => $this->created_by,
            'links' => $this->whenLoaded('links', fn () => $this->links->map(fn ($link) => ['id' => $link->id, 'entityType' => $link->entity_type, 'entityId' => $link->entity_id, 'createdAt' => $link->created_at])),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
