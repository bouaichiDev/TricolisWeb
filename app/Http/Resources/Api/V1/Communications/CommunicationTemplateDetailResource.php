<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Modules\Communications\Models\CommunicationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Modèle de message, forme complète.
 *
 * Les règles et communications qui en découlent ne sont pas incluses : leurs
 * décomptes suffisent à savoir si le modèle est supprimable, et les charger
 * ferait grossir une réponse sans besoin.
 *
 * @mixin CommunicationTemplate
 */
class CommunicationTemplateDetailResource extends JsonResource
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
            'subjectTemplate' => $this->subject_template,
            'bodyTemplate' => $this->body_template,
            'language' => $this->language,
            'availableVariables' => $this->available_variables,
            'isDefault' => $this->is_default,
            'isActive' => $this->is_active,
            'rulesCount' => $this->whenCounted('rules'),
            'communicationsCount' => $this->whenCounted('communications'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
