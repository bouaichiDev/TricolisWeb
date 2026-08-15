<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Modules\Communications\Models\CommunicationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Modèle de message, forme réduite.
 *
 * Sert de valeur imbriquée dans une règle ou une communication : ni corps, ni
 * objet, ni variables. Le §37 l'exige — charger le contenu de chaque modèle dans
 * une liste de communications le répéterait à chaque ligne.
 *
 * @mixin CommunicationTemplate
 */
class CommunicationTemplateCompactResource extends JsonResource
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
