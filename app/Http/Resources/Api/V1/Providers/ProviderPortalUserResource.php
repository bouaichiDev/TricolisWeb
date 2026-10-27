<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Providers;

use App\Modules\ProviderPortal\Models\ProviderUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un accès au portail fournisseur, tel que le back-office le voit.
 *
 * @mixin ProviderUser
 */
class ProviderPortalUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'invitedAt' => $this->invited_at?->toIso8601String(),
            'activatedAt' => $this->activated_at?->toIso8601String(),
            'disabledAt' => $this->disabled_at?->toIso8601String(),
            'user' => [
                'id' => $this->user->id,
                'firstName' => $this->user->first_name,
                'lastName' => $this->user->last_name,
                'email' => $this->user->email,
                'lastLoginAt' => $this->user->last_login_at?->toIso8601String(),
            ],
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(static fn ($role): array => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
            ])->values()->all()),
        ];
    }
}
