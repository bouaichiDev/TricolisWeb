<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Modules\Communications\Models\CommunicationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Règle de communication, forme complète.
 *
 * @mixin CommunicationRule
 */
class CommunicationRuleDetailResource extends JsonResource
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
            'templateId' => $this->template_id,
            'eventType' => $this->event_type?->value,
            'recipientRole' => $this->recipient_role?->value,
            'delayValue' => $this->delay_value,
            'delayUnit' => $this->delay_unit,
            'conditions' => $this->conditions,
            'isAutomatic' => $this->is_automatic,
            'isActive' => $this->is_active,
            'communicationsCount' => $this->whenCounted('communications'),
            'template' => new CommunicationTemplateCompactResource($this->whenLoaded('template')),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
