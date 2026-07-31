<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Modules\Identity\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permission */
class PermissionResource extends JsonResource
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
            'module' => $this->module,
            'action' => $this->action,
        ];
    }
}
