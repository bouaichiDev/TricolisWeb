<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Documents;

use App\Modules\Documents\Models\DocumentLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentLink */
class DocumentLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documentId' => $this->document_id,
            'entityType' => $this->entity_type,
            'entityId' => $this->entity_id,
            'createdAt' => $this->created_at,
        ];
    }
}
