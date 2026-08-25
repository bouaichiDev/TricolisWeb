<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Types;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une source de valeurs.
 *
 * `isSystem` dit à l'interface qu'une colonne du schéma s'y réfère : la source
 * se renomme, mais son code et sa suppression sont fermés.
 */
class TypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'isSystem' => (bool) $this->is_system,
            'itemCount' => $this->whenCounted('items'),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
