<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Http\Resources\Api\V1\Templates\TemplateCompactResource;
use App\Modules\Communications\Models\CommunicationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Règle de communication en liste.
 *
 * `conditions` est absent : c'est une structure, elle appartient au détail.
 *
 * @mixin CommunicationRule
 */
class CommunicationRuleListResource extends JsonResource
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
            'isAutomatic' => $this->is_automatic,
            'isActive' => $this->is_active,
            'template' => new TemplateCompactResource($this->whenLoaded('template')),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
