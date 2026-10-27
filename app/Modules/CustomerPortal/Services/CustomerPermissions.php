<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Support\CustomerFeatureGate;
use App\Modules\CustomerPortal\Support\CustomerPortalPermissions;
use App\Modules\Identity\Models\Role;
use App\Shared\Enums\RoleScope;

/**
 * Permissions effectives d'un membre du portail.
 *
 * Trois filtres, et aucun ne lit un nom de rôle :
 *
 * 1. seuls comptent les rôles actifs, de portée `customer`, appartenant à
 *    l'organisation qui gère ce client ;
 * 2. seules comptent les permissions `customer.*` qu'ils portent — un rôle
 *    mal composé ne fait jamais entrer un droit interne dans le portail ;
 * 3. l'ensemble est borné par les modules ouverts au client.
 *
 * `is_owner` ne confère rien de plus : il protège le compte, il n'élargit pas
 * ses droits.
 */
final class CustomerPermissions
{
    /**
     * @return array<int, string>
     */
    public static function codesFor(CustomerUser $membership): array
    {
        $membership->loadMissing(['customer', 'roles.permissions:id,code']);
        $customer = $membership->customer;
        $codes = [];

        foreach ($membership->roles as $role) {
            if (! self::countsFor($role, $customer->organization_id)) {
                continue;
            }

            foreach ($role->permissions as $permission) {
                $code = $permission->code;

                if (CustomerPortalPermissions::isCustomerPermission($code)
                    && CustomerFeatureGate::allows($customer, $code)) {
                    $codes[$code] = true;
                }
            }
        }

        $codes = array_keys($codes);
        sort($codes);

        return $codes;
    }

    /**
     * Permissions tenues des rôles, **avant** le filtre des modules du client.
     *
     * C'est la mesure du plafond de délégation : un administrateur dont le
     * client n'a pas de stock doit pouvoir inviter un autre administrateur,
     * dont le rôle porte pourtant `customer.stock.*`. Le filtre des modules
     * s'appliquera au nouveau venu comme à lui.
     *
     * @return array<int, string>
     */
    public static function delegableFor(CustomerUser $membership): array
    {
        $membership->loadMissing(['customer', 'roles.permissions:id,code']);
        $organizationId = $membership->customer->organization_id;

        return $membership->roles
            ->filter(static fn (Role $role): bool => self::countsFor($role, $organizationId))
            ->flatMap(static fn (Role $role): array => self::codesOfRole($role))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Permissions portées par un rôle, sans le filtre des modules du client.
     *
     * Sert au plafond de délégation : on compare ce que le rôle transmettrait
     * à ce que l'attribuant détient.
     *
     * @return array<int, string>
     */
    public static function codesOfRole(Role $role): array
    {
        return $role->permissions
            ->pluck('code')
            ->filter(static fn (string $code): bool => CustomerPortalPermissions::isCustomerPermission($code))
            ->values()
            ->all();
    }

    public static function countsFor(Role $role, string $organizationId): bool
    {
        return $role->scope === RoleScope::CUSTOMER->value
            && $role->organization_id === $organizationId
            && $role->status === 'active';
    }
}
