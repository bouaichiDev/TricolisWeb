<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Orders;

use App\Modules\Orders\Models\OrderServiceContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contact d'un service, restitué depuis son snapshot.
 *
 * Les valeurs renvoyées sont celles figées à la création : elles restent
 * fidèles à la commande même si le contact partagé a changé depuis.
 *
 * @mixin OrderServiceContact
 */
class OrderServiceContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderServiceId' => $this->order_service_id,
            'contactId' => $this->contact_id,
            'contactRole' => $this->contact_role?->value,
            'firstName' => $this->first_name_snapshot,
            'lastName' => $this->last_name_snapshot,
            'phone' => $this->phone_snapshot,
            'mobile' => $this->mobile_snapshot,
            'email' => $this->email_snapshot,
            'isPrimary' => $this->is_primary,
            'createdAt' => $this->created_at,
        ];
    }
}
