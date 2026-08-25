<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Types;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une valeur de référentiel.
 *
 * `typeCode` évite un second appel pour savoir de quelle source elle vient :
 * une liste mélangée serait illisible sans lui.
 */
class TypeItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'typeId' => $this->type_id,
            'typeCode' => $this->whenLoaded('type', fn () => $this->type->code),
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status,
            'position' => $this->position,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
