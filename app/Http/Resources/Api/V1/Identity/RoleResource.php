<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'organizationId' => $this->organization_id, 'code' => $this->code, 'name' => $this->name, 'scope' => $this->scope, 'isSystem' => $this->is_system, 'status' => $this->status, 'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->map(fn ($permission) => ['id' => $permission->id, 'code' => $permission->code, 'name' => $permission->name, 'module' => $permission->module, 'action' => $permission->action]))];
    }
}
