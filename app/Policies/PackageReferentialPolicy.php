<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Packages\Models\GroupingType;
use App\Modules\Packages\Models\PackageType;

/**
 * Référentiels du module Colis : types de colis et types de regroupement.
 *
 * Les deux partagent les permissions `packages.*` : le cahier des charges ne
 * prévoit pas de permission dédiée, et en inventer une produirait un code que
 * rien d'autre ne vérifie.
 */
class PackageReferentialPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'packages.view');
    }

    public function view(User $user, PackageType|GroupingType $type): bool
    {
        return $this->hasPermission($user, $type->organization_id, 'packages.view');
    }

    public function create(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'packages.create');
    }

    public function update(User $user, PackageType|GroupingType $type): bool
    {
        return $this->hasPermission($user, $type->organization_id, 'packages.update');
    }

    public function delete(User $user, PackageType|GroupingType $type): bool
    {
        return $this->hasPermission($user, $type->organization_id, 'packages.delete');
    }
}
