<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Modules\Communications\Models\CommunicationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Modèle de message en liste.
 *
 * Ni `subjectTemplate`, ni `bodyTemplate` : le corps est un LONGTEXT, et le
 * §37 interdit de charger l'inutile dans une liste. Le détail les porte.
 *
 * @mixin CommunicationTemplate
 */
class CommunicationTemplateListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'serviceId' => $this->service_id,
            'code' => $this->code,
            'name' => $this->name,
            'channel' => $this->channel?->value,
            'templateType' => $this->template_type?->value,
            'language' => $this->language,
            'isDefault' => $this->is_default,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
