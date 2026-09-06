<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Platform;

use App\Modules\Platform\Models\AccessRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccessRequest */
class AccessRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'companyName' => $this->company_name,
            'contactName' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'message' => $this->message,
            'status' => $this->status->value,
            'decisionNote' => $this->decision_note,
            'decidedAt' => $this->decided_at,
            // L'organisation née de la demande : c'est le lien qui mène de la
            // décision à son résultat, et il n'existe qu'après l'acceptation.
            'organizationId' => $this->organization_id,
            'createdAt' => $this->created_at,
        ];
    }
}
