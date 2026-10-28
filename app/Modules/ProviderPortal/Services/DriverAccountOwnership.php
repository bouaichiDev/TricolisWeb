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
        return in_array($driver->id, self::ownedAmong([$driver]), true);
    }

    /**
     * La même règle pour une page entière, en quatre requêtes quel que soit le
     * nombre de chauffeurs (les relations `user` doivent être chargées).
     *
     * @param  iterable<Driver>  $drivers
     * @return list<string> identifiants des chauffeurs dont le compte est propre
     */
    public static function ownedAmong(iterable $drivers): array
    {
        $candidates = collect($drivers)->filter(static fn (Driver $driver): bool => $driver->user?->status === UserStatus::INVITED);
        $users = $candidates->pluck('user_id')->unique()->values();

        if ($users->isEmpty()) {
            return [];
        }

        $memberships = OrganizationUser::with('roles:id,code')->whereIn('user_id', $users)->get()->groupBy('user_id');
        $portal = ProviderUser::whereIn('user_id', $users)->pluck('user_id')
            ->merge(CustomerUser::whereIn('user_id', $users)->pluck('user_id'))->flip();
        $driverCounts = Driver::whereIn('user_id', $users)->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id');

        return $candidates->filter(static function (Driver $driver) use ($memberships, $portal, $driverCounts): bool {
            $own = $memberships->get($driver->user_id, collect());
            $membership = $own->first();

            return $own->count() === 1
                && $membership->organization_id === $driver->organization_id
                && $membership->roles->pluck('code')->all() === [RoleSeeder::DRIVER_CODE]
                && ! $portal->has($driver->user_id)
                && (int) $driverCounts->get($driver->user_id, 0) === 1;
        })->pluck('id')->values()->all();
    }
}
