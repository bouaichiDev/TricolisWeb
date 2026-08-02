<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Modules\Communications\Models\OrderCommunication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Communication en liste.
 *
 * Ni `body`, ni `templateVariables`, ni `providerResponse` : le corps est un
 * LONGTEXT et la réponse fournisseur une structure technique. Le détail les
 * porte.
 *
 * @mixin OrderCommunication
 */
class OrderCommunicationListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'orderId' => $this->order_id,
            'templateId' => $this->template_id,
            'communicationRuleId' => $this->communication_rule_id,
            'channel' => $this->channel?->value,
            'communicationType' => $this->communication_type?->value,
            'recipientRole' => $this->recipient_role?->value,
            'recipientName' => $this->recipient_name,
            'recipientEmail' => $this->recipient_email,
            'recipientPhone' => $this->recipient_phone,
            'subject' => $this->subject,
            'status' => $this->status?->value,
            'scheduledAt' => $this->scheduled_at?->toIso8601String(),
            'sentAt' => $this->sent_at?->toIso8601String(),
            'failedAt' => $this->failed_at?->toIso8601String(),
            'attachmentsCount' => $this->whenCounted('attachments'),
            'template' => new CommunicationTemplateCompactResource($this->whenLoaded('template')),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
