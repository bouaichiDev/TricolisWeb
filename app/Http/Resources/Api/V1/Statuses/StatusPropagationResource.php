<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Statuses;

use App\Modules\Statuses\Models\StatusPropagation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une règle de propagation, avec ses deux statuts en clair.
 *
 * L'écran doit pouvoir lire « tous les colis chargés → service chargé » sans
 * redemander chaque statut : l'entité, le code et le libellé accompagnent donc
 * les identifiants.
 *
 * @mixin StatusPropagation
 */
class StatusPropagationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fromStatusId' => $this->from_status_id,
            'toStatusId' => $this->to_status_id,
            'from' => $this->describe('fromStatus'),
            'to' => $this->describe('toStatus'),
            'mode' => $this->mode,
            'active' => $this->active,
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    private function describe(string $relation): ?array
    {
        $status = $this->resource->{$relation};

        return $status === null ? null : [
            'source' => $status->source,
            'code' => $status->code,
            'label' => $status->label,
        ];
    }
}
