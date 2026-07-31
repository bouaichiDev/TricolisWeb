<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Contacts;

use App\Modules\Contacts\Models\EntityContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EntityContact */
class EntityContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contactId' => $this->contact_id,
            'entityType' => $this->entity_type,
            'entityId' => $this->entity_id,
            'contactRole' => $this->contact_role,
            'isPrimary' => $this->is_primary,
            'notifyByEmail' => $this->notify_by_email,
            'notifyBySms' => $this->notify_by_sms,
            'contact' => new ContactResource($this->whenLoaded('contact')),
        ];
    }
}
