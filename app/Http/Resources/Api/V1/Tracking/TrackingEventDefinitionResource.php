<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tracking;

use App\Modules\Tracking\Models\TrackingEventDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TrackingEventDefinition */
class TrackingEventDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'sourceType' => $this->source_type,
            'statusCode' => $this->status_code,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'position' => $this->position,
            'apiConfigurationId' => $this->api_configuration_id,
            'serviceId' => $this->service_id,
            // Nomme, et pas seulement identifie : un ecran qui liste des etapes
            // ne doit pas charger le catalogue pour dire de quoi elles parlent.
            'serviceName' => $this->whenLoaded('service', fn (): ?string => $this->service?->name),
            'visibleToCustomer' => $this->visible_to_customer,
            'showsProofOfDelivery' => $this->shows_proof_of_delivery,
            // Derive : une etape est suivie en direct si une API la renseigne.
            'isLive' => $this->api_configuration_id !== null,
            'active' => $this->active,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
