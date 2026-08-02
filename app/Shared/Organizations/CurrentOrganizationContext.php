<?php

declare(strict_types=1);

namespace App\Shared\Organizations;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Contexte de sécurité de la requête courante : organisation active,
 * rattachement, rôles et permissions.
 *
 * La requête et le garde sont résolus à chaque appel, jamais injectés dans le
 * constructeur : l'instance étant partagée pour la durée du scope, mémoriser la
 * requête ferait fuiter l'organisation d'un appel sur le suivant.
 */
final readonly class CurrentOrganizationContext
{
    public function getOrganizationId(): ?string
    {
        $header = request()->header('X-Organization-Id');

        if ($header === null || $header === '') {
            return null;
        }

        if (! Str::isUlid($header)) {
            throw new InvalidArgumentException('L\'identifiant d\'organisation est invalide.');
        }

        return $header;
    }

    public function getUser(): ?User
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user;
    }

    public function getOrganizationUser(): ?OrganizationUser
    {
        $user = $this->getUser();
        $organizationId = $this->getOrganizationId();

        if ($user === null || $organizationId === null) {
            return null;
        }

        return OrganizationUser::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->with('roles.permissions')
            ->first();
    }

    public function getOrganization(): ?Organization
    {
        $organizationId = $this->getOrganizationId();

        if ($organizationId === null) {
            return null;
        }

        return Organization::where('id', $organizationId)->first();
    }

    public function hasPermission(string $permission): bool
    {
        $organizationUser = $this->getOrganizationUser();

        if ($organizationUser === null) {
            return false;
        }

        if ($organizationUser->is_owner) {
            return true;
        }

        foreach ($organizationUser->roles as $role) {
            if ($role->permissions->contains('code', $permission)) {
                return true;
            }
        }

        return false;
    }

    public function ensureBelongsToOrganization(string $organizationId): void
    {
        $current = $this->getOrganizationId();

        if ($current === null || $current !== $organizationId) {
            throw new InvalidArgumentException('L\'organisation demandée n\'est pas active.');
        }
    }

    public function hasOrganizationAccess(): bool
    {
        return $this->getOrganizationUser() !== null;
    }
}
