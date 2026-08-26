<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Modules\Drivers\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'organizationId' => $this->organization_id, 'userId' => $this->user_id, 'isOwner' => $this->is_owner, 'isPrimary' => $this->is_primary, 'status' => $this->status?->value, 'joinedAt' => $this->joined_at, 'user' => ['firstName' => $this->user->first_name, 'lastName' => $this->user->last_name, 'email' => $this->user->email, 'phone' => $this->user->phone, 'preferredLanguage' => $this->user->preferred_language], 'roles' => $this->roles->map(fn ($role) => ['id' => $role->id, 'code' => $role->code, 'name' => $role->name]), 'driver' => $this->driverOf()];
    }

    /**
     * Le chauffeur que ce membre est, s'il en est un.
     *
     * Le lien se lit dans les deux sens : depuis le chauffeur on atteint son
     * compte, et depuis le compte on retrouve le chauffeur. Sans cela, un
     * administrateur regardant un membre ne saurait pas qu'il conduit.
     *
     * **La relation doit etre chargee par l'appelant.** Une lecture directe ici
     * ferait une requete par membre : le budget de la liste passait de 20 a 37
     * requetes, ce qu'un test d'endurance a rattrape.
     *
     * @return array{id: string, code: string, name: string}|null
     */
    private function driverOf(): ?array
    {
        $driver = $this->whenLoaded('driver');

        return $driver instanceof Driver
            ? ['id' => $driver->id, 'code' => $driver->code, 'name' => $driver->name]
            : null;
    }
}
