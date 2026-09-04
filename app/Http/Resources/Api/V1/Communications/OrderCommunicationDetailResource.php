<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Http\Resources\Api\V1\Identity\UserCompactResource;
use App\Http\Resources\Api\V1\Templates\TemplateCompactResource;
use App\Modules\Communications\Models\OrderCommunication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Communication, forme complète.
 *
 * `providerResponse` n'est retournée qu'après filtrage : le §37 impose de
 * masquer ce qu'elle pourrait contenir de sensible. Les transporteurs livrés
 * n'y placent que des métadonnées d'acheminement, mais un fournisseur futur
 * pourrait y renvoyer un jeton — la liste blanche protège d'avance.
 *
 * @mixin OrderCommunication
 */
class OrderCommunicationDetailResource extends JsonResource
{
    /**
     * Clés de `providerResponse` exposables.
     *
     * @var list<string>
     */
    private const array PUBLIC_PROVIDER_KEYS = ['channel', 'mailer', 'delivery', 'configured', 'status', 'code'];

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
            'body' => $this->body,
            'templateVariables' => $this->template_variables,
            'status' => $this->status?->value,
            'scheduledAt' => $this->scheduled_at?->toIso8601String(),
            'queuedAt' => $this->queued_at?->toIso8601String(),
            'sentAt' => $this->sent_at?->toIso8601String(),
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'readAt' => $this->read_at?->toIso8601String(),
            'failedAt' => $this->failed_at?->toIso8601String(),
            'providerMessageId' => $this->provider_message_id,
            'providerResponse' => $this->filteredProviderResponse(),
            'errorMessage' => $this->error_message,
            'createdBy' => $this->created_by,
            'creator' => new UserCompactResource($this->whenLoaded('creator')),
            'template' => new TemplateCompactResource($this->whenLoaded('template')),
            'attachments' => CommunicationAttachmentResource::collection($this->whenLoaded('attachments')),
            'attachmentsCount' => $this->whenCounted('attachments'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function filteredProviderResponse(): ?array
    {
        $response = $this->provider_response;

        if (! is_array($response)) {
            return null;
        }

        return array_intersect_key($response, array_flip(self::PUBLIC_PROVIDER_KEYS));
    }
}
