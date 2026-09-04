<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Contacts;

use App\Modules\Contacts\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Contact */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'preferredLanguage' => $this->preferred_language,
            'isActive' => $this->is_active,
            /**
             * Liaisons vers les entités qui déclarent ce contact.
             *
             * Chargées seulement lorsque l'appelant a filtré par entité. C'est
             * la liaison qui porte le rôle — livraison, facturation, urgence —
             * et le drapeau principal : le contact lui-même n'en sait rien, et
             * peut tenir des rôles différents selon l'entité.
             */
            'links' => $this->whenLoaded('entityContacts', fn () => $this->entityContacts->map(fn ($link) => [
                'id' => $link->id,
                'entityType' => $link->entity_type,
                'entityId' => $link->entity_id,
                'contactRole' => $link->contact_role?->value ?? $link->contact_role,
                'isPrimary' => (bool) $link->is_primary,
                'notifyByEmail' => (bool) $link->notify_by_email,
                'notifyBySms' => (bool) $link->notify_by_sms,
            ])),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
