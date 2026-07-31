<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'organizationId' => $this->organization_id, 'userId' => $this->user_id, 'isOwner' => $this->is_owner, 'isPrimary' => $this->is_primary, 'status' => $this->status?->value, 'joinedAt' => $this->joined_at, 'user' => ['firstName' => $this->user->first_name, 'lastName' => $this->user->last_name, 'email' => $this->user->email, 'phone' => $this->user->phone, 'preferredLanguage' => $this->user->preferred_language], 'roles' => $this->roles->map(fn ($role) => ['id' => $role->id, 'code' => $role->code, 'name' => $role->name])];
    }
}
