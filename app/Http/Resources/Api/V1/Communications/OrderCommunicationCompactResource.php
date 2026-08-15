<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Communications;

use App\Modules\Communications\Models\OrderCommunication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Communication réduite à ce qui l'identifie.
 *
 * @mixin OrderCommunication
 */
class OrderCommunicationCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel?->value,
            'communicationType' => $this->communication_type?->value,
            'recipientName' => $this->recipient_name,
            'status' => $this->status?->value,
            'sentAt' => $this->sent_at?->toIso8601String(),
        ];
    }
}
