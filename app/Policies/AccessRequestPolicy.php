<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PlatformAccess;

/**
 * Qui lit et qui tranche les demandes d'accès.
 *
 * `hasPlatformPermission` et non `hasPermission` : celle-ci est bornée à une
 * organisation, et une demande d'accès n'en concerne aucune — elle en fera
 * naître une. Un propriétaire d'organisme, qui détient pourtant tout chez lui,
 * n'a rien à décider ici.
 *
 * **Deux permissions existantes, aucune nouvelle.** Lire les demandes relève de
 * `organizations.view`, et les trancher de `organizations.create` : accepter
 * *est* créer une organisation, et inventer un code de permission de plus
 * n'aurait fait qu'un réglage supplémentaire à distribuer pour le même geste.
 */
class AccessRequestPolicy
{
    public function __construct(private readonly PlatformAccess $platform) {}

    public function viewAny(User $user): bool
    {
        return $this->platform->hasPlatformPermission($user, 'organizations.view');
    }

    public function decide(User $user): bool
    {
        return $this->platform->hasPlatformPermission($user, 'organizations.create');
    }
}
