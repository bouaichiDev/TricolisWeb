<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Identity\Models\Role;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Support\ProviderPortalPermissions;
use App\Shared\Enums\RoleScope;

/**
 * Permissions effectives d'un membre du portail fournisseur.
 *
 * Un rôle ne compte que s'il est de portée `provider`, actif, et de la même
 * organisation que le fournisseur : un rôle d'un autre transporteur attaché
 * par erreur ne donne rien. Seules les permissions `provider.*` sont retenues.
 */
final class ProviderPermissions
{
    /**
     * @return array<int, string>
     */
    public static function codesFor(ProviderUser $membership): array
    {
        $membership->loadMissing(['provider', 'roles.permissions:id,code']);
        $organizationId = $membership->provider->organization_id;
        $codes = [];

        foreach ($membership->roles as $role) {
            if (! self::countsFor($role, $organizationId)) {
                continue;
            }

            foreach ($role->permissions as $permission) {
                if (ProviderPortalPermissions::isProviderPermission($permission->code)) {
                    $codes[$permission->code] = true;
                }
            }
        }

        $codes = array_keys($codes);
        sort($codes);

        return $codes;
    }

    public static function countsFor(Role $role, string $organizationId): bool
    {
        return $role->scope === RoleScope::PROVIDER->value
            && $role->organization_id === $organizationId
            && $role->status === 'active';
    }
}
