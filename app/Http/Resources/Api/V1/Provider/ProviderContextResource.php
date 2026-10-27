<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderPermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tout ce que le portail fournisseur doit savoir pour se dessiner : qui, pour
 * quel fournisseur, avec quels droits. Les permissions sont celles que le
 * backend appliquera — le frontend s'en sert pour masquer, jamais pour
 * protéger. Seuls les rôles qui comptent réellement sont listés.
 *
 * @property-read CurrentProviderContext $resource
 */
class ProviderContextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = $this->resource;
        $membership = $context->membership()->loadMissing('roles');
        $provider = $context->provider()->loadMissing('organization');
        $user = $context->user();

        return [
            'user' => [
                'id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
            ],
            'provider' => [
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
            ],
            'carrier' => [
                'name' => $provider->organization?->name,
            ],
            'roles' => $membership->roles
                ->filter(static fn ($role): bool => ProviderPermissions::countsFor($role, $provider->organization_id))
                ->map(static fn ($role): array => ['code' => $role->code, 'name' => $role->name])
                ->values()->all(),
            'permissions' => $context->permissions(),
        ];
    }
}
