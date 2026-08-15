<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

abstract class BaseOrganizationPolicy
{
    /**
     * Refus qui se présente comme une absence.
     *
     * Ajouté en Phase 10. Les deux refus possibles ne doivent pas se ressembler :
     *
     * - **permission manquante** sur une ressource visible → `403`, l'appelant
     *   sait quoi demander à son administrateur ;
     * - **ressource d'une autre organisation** → `404`, car un `403` confirmerait
     *   que cet identifiant existe ailleurs. C'est la différence entre les deux
     *   réponses qui constitue la fuite, pas leur contenu.
     *
     * La convention était tenue par les Phases 4 à 9 via les contrôleurs ;
     * cinq ressources des Phases 1 et 2 y échappaient encore.
     */
    protected function notFound(): Response
    {
        return Response::denyAsNotFound('Ressource introuvable.');
    }

    /**
     * L'utilisateur a-t-il accès à l'organisation portant cette ressource ?
     */
    protected function seesOrganization(User $user, ?string $organizationId): bool
    {
        return $organizationId !== null && $this->hasOrganizationAccess($user, $organizationId);
    }

    protected function hasOrganizationAccess(User $user, ?string $organizationId): bool
    {
        if ($organizationId === null) {
            return false;
        }

        return OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->exists();
    }

    protected function hasPermission(User $user, ?string $organizationId, string $permission): bool
    {
        if ($organizationId === null) {
            return false;
        }

        $organizationUser = OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->with('roles.permissions')
            ->first();

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

    protected function belongsToOrganization(Model $model, string $organizationId): bool
    {
        return $model->getAttribute('organization_id') === $organizationId;
    }
}
