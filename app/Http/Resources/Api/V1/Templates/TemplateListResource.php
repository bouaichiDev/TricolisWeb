<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Templates;

use App\Modules\Templates\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Modèle en liste.
 *
 * Ni `subjectTemplate`, ni `bodyTemplate` : le corps est un LONGTEXT, et le
 * §37 interdit de charger l'inutile dans une liste. Le détail les porte.
 *
 * @mixin Template
 */
class TemplateListResource extends JsonResource
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
            'language' => $this->language,
            'isDefault' => $this->is_default,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
