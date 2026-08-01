<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Packages;

use App\Modules\Packages\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Colis et sa descendance.
 *
 * L'arbre est construit en mémoire à partir d'une seule requête plate : le
 * chargement récursif produirait une requête par niveau.
 *
 * @mixin Package
 */
class PackageTreeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parentPackageId' => $this->parent_package_id,
            'barcode' => $this->barcode,
            'reference' => $this->reference,
            'quantity' => $this->quantity,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'status' => $this->status,
            'children' => self::collection($this->children ?? collect()),
        ];
    }
}
