<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Utilisateur vu depuis l'annuaire d'une organisation.
 *
 * Le hash du mot de passe et le jeton de session ne sont jamais exposés.
 *
 * @mixin User
 */
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
            'memberships' => $this->whenLoaded('organizationUsers', fn () => $this->organizationUsers->map(fn ($membership) => [
                'id' => $membership->id,
                'organizationId' => $membership->organization_id,
                'isOwner' => $membership->is_owner,
                'isPrimary' => $membership->is_primary,
                'status' => $membership->status?->value,
            ])),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
