<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Templates;

use App\Modules\Templates\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Modèle, forme réduite.
 *
 * Sert de valeur imbriquée dans une règle ou une communication : ni corps, ni
 * objet, ni variables. Le §37 l'exige — charger le contenu de chaque modèle dans
 * une liste de communications le répéterait à chaque ligne.
 *
 * @mixin Template
 */
class TemplateCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'channel' => $this->channel?->value,
        ];
    }
}
