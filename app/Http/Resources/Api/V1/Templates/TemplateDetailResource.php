<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Templates;

use App\Modules\Templates\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Modèle, forme complète.
 *
 * Les règles et communications qui en découlent ne sont pas incluses : leurs
 * décomptes suffisent à savoir si le modèle est supprimable, et les charger
 * ferait grossir une réponse sans besoin.
 *
 * @mixin Template
 */
class TemplateDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'customerId' => $this->customer_id,
            'customerName' => $this->whenLoaded('customer', fn (): ?string => $this->customer?->name),
            'serviceId' => $this->service_id,
            'serviceName' => $this->whenLoaded('service', fn (): ?string => $this->service?->name),
            'scope' => $this->scope(),
            'code' => $this->code,
            'name' => $this->name,
            'channel' => $this->channel?->value,
            'templateType' => $this->template_type?->value,
            'subjectTemplate' => $this->subject_template,
            'bodyTemplate' => $this->body_template,
            'bodyFormat' => $this->body_format,
            'language' => $this->language,
            'availableVariables' => $this->available_variables,
            'isDefault' => $this->is_default,
            'isActive' => $this->is_active,
            'rulesCount' => $this->whenCounted('rules'),
            'communicationsCount' => $this->whenCounted('communications'),
            'invoicesCount' => $this->whenCounted('invoices'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
