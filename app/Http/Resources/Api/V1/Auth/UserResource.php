<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
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
            'fullName' => $this->fullName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'preferredLanguage' => $this->preferred_language,
            'status' => $this->status?->value,
            'emailVerifiedAt' => $this->email_verified_at,
            'lastLoginAt' => $this->last_login_at,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'organizations' => $this->whenLoaded('organizationUsers', fn () => $this->organizationUsers->map(fn ($membership) => [
                'id' => $membership->organization->id,
                'code' => $membership->organization->code,
                'name' => $membership->organization->name,
                'isOwner' => $membership->is_owner,
                'isPrimary' => $membership->is_primary,
                'roles' => $membership->roles->map(fn ($role) => ['id' => $role->id, 'code' => $role->code, 'name' => $role->name]),
                'permissions' => $membership->roles->flatMap->permissions->unique('id')->values()->map(fn ($permission) => ['id' => $permission->id, 'code' => $permission->code]),
                'agencies' => $membership->organization->agencies->map(fn ($agency) => ['id' => $agency->id, 'code' => $agency->code, 'name' => $agency->name]),
            ])),
        ];
    }
}
