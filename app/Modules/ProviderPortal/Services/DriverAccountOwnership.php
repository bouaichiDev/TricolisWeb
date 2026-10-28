<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RoleSeeder;

/**
 * Le compte d'un chauffeur lui est-il **propre** ?
 *
 * Les comptes sont globaux : le même `User` peut servir chez un autre
 * transporteur, dans un portail client ou fournisseur, ou comme collaborateur
 * interne. Le fournisseur ne touche à l'identité du compte que s'il n'a pas
 * d'autre vie : encore invité, une seule appartenance — dans l'organisation du
 * chauffeur, avec le seul rôle chauffeur —, aucun accès portail, un seul
 * chauffeur.
 */
final class DriverAccountOwnership
{
    public static function owns(Driver $driver): bool
    {
        $user = $driver->user;

        if ($user === null || $user->status !== UserStatus::INVITED) {
            return false;
        }

        $memberships = OrganizationUser::with('roles:id,code')->where('user_id', $user->id)->get();
        $membership = $memberships->first();

        return $memberships->count() === 1
            && $membership->organization_id === $driver->organization_id
            && $membership->roles->pluck('code')->all() === [RoleSeeder::DRIVER_CODE]
            && ! ProviderUser::where('user_id', $user->id)->exists()
            && ! CustomerUser::where('user_id', $user->id)->exists()
            && Driver::where('user_id', $user->id)->whereKeyNot($driver->id)->doesntExist();
    }
}
