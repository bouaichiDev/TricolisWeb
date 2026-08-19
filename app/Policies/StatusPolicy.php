<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PlatformAccess;
use App\Modules\Statuses\Models\Status;

/**
 * Deux niveaux d'autorité sur le référentiel des statuts.
 *
 * **Tout membre le lit.** Les écrans en ont besoin pour afficher un libellé et
 * une icône à la place d'un code brut ; le refuser rendrait l'interface muette.
 *
 * **Seule la plateforme l'écrit.** Un statut décrit le cycle de vie du domaine,
 * pas la préférence d'un organisme : laisser un administrateur local renommer
 * « confirmée » ou en supprimer un rendrait les commandes de deux organismes
 * incomparables, et casserait les exports comme les échanges.
 *
 * Le contrôle est double — portée **et** permission — comme pour les
 * organisations : un rôle plateforme incomplet ne suffit pas.
 */
class StatusPolicy
{
    public function __construct(private readonly PlatformAccess $platform) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Status $status): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->platform->hasPlatformPermission($user, 'statuses.create');
    }

    public function update(User $user, Status $status): bool
    {
        return $this->platform->hasPlatformPermission($user, 'statuses.update');
    }

    /**
     * Supprimer un statut.
     *
     * Le contrôleur vérifie en plus qu'aucun enregistrement ne le porte encore :
     * l'autorisation dit qui a le droit, pas si l'opération est possible.
     */
    public function delete(User $user, Status $status): bool
    {
        return $this->platform->hasPlatformPermission($user, 'statuses.delete');
    }
}
