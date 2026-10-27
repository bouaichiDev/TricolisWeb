<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use App\Modules\CustomerPortal\Services\CustomerPermissions;
use App\Modules\Identity\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class CustomerPortalRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'permissions' => CustomerPermissions::codesOfRole($this->resource),
        ];
    }
}
