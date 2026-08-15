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
            /**
             * Découpe métier, distincte du module qui est technique.
             *
             * Le formulaire de rôle groupe dessus : les 48 modules du
             * référentiel donnaient 48 sections, dans lesquelles composer un
             * rôle était impraticable.
             */
            'menuSection' => $this->menu_section,
            'action' => $this->action,
        ];
    }
}
