<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Audit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'organizationId' => $this->organization_id, 'userId' => $this->user_id, 'action' => $this->action, 'entityType' => $this->entity_type, 'entityId' => $this->entity_id, 'oldValues' => $this->old_values, 'newValues' => $this->new_values, 'ipAddress' => $this->ip_address, 'createdAt' => $this->created_at];
    }
}
