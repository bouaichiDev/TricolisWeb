<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Stock;

use App\Modules\Stock\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nœud de l'arbre des emplacements, récursif.
 *
 * Les enfants sont déjà attachés en mémoire par `StockLocationListQuery::tree()`
 * — un seul `SELECT` pour tout l'arbre, aucune requête par niveau.
 *
 * @mixin StockLocation
 */
class StockLocationTreeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'depotId' => $this->depot_id,
            'parentLocationId' => $this->parent_location_id,
            'zoneCode' => $this->zone_code,
            'locationCode' => $this->location_code,
            'status' => $this->status,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
