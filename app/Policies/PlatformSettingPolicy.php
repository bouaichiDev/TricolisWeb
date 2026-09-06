<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PlatformAccess;

/**
 * Qui règle la plateforme.
 *
 * `hasPlatformPermission` et non `hasPermission` : celle-ci est bornée à une
 * organisation, alors qu'un rôle plateforme n'en a aucune. Un propriétaire
 * d'organisme, qui détient pourtant tout chez lui, n'a rien à décider ici.
 *
 * **Il n'y a pas d'`view`.** La lecture — « y a-t-il un logo par défaut ? » — est
 * ouverte à tout compte authentifié : la barre latérale de chacun en dépend, et
 * la protéger obligerait à distribuer une permission plateforme pour afficher
 * une image de marque. Ce qui se garde, c'est l'écriture.
 */
class PlatformSettingPolicy
{
    public function __construct(private readonly PlatformAccess $platform) {}

    public function update(User $user): bool
    {
        return $this->platform->hasPlatformPermission($user, 'platform_settings.update');
    }
}
