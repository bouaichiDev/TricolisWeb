<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Types\Models\Type;
use Illuminate\Auth\Access\Response;

/**
 * Les référentiels de type, sources et valeurs confondues.
 *
 * Une permission unique — `types.*` — remplace `vehicle_types.*` et les droits
 * des référentiels de colis : une source ajoutée par l'organisme serait sinon
 * inaccessible jusqu'à ce qu'on lui écrive sa permission.
 */
class TypePolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'types.view');
    }

    public function view(User $user, Type $type): Response|bool
    {
        return $this->scoped($user, $type, 'types.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'types.create');
    }

    public function update(User $user, Type $type): Response|bool
    {
        return $this->scoped($user, $type, 'types.update');
    }

    /**
     * Une source structurelle ne se supprime pas.
     *
     * `vehicles.vehicle_type_id` et `packages.package_type_id` la désignent :
     * la faire disparaître laisserait ces colonnes sans cible, quelle que soit
     * la permission de qui le demande.
     */
    public function delete(User $user, Type $type): Response|bool
    {
        if (! $this->seesOrganization($user, $type->organization_id)) {
            return $this->notFound();
        }

        if ($type->is_system) {
            return Response::deny('Cette source est structurelle : elle ne peut pas être supprimée.');
        }

        return $this->hasPermission($user, $type->organization_id, 'types.delete');
    }

    private function scoped(User $user, Type $type, string $permission): Response|bool
    {
        if (! $this->seesOrganization($user, $type->organization_id)) {
            return $this->notFound();
        }

        return $this->hasPermission($user, $type->organization_id, $permission);
    }
}
