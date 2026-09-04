<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;

/**
 * Les deux portes du tableau de bord.
 *
 * La politique est rattachée à `RoleDashboardConfiguration` parce qu'il faut
 * bien un modèle pour s'y accrocher, mais elle ne garde pas cette table : elle
 * garde **l'écran** et **son réglage**, qui n'ont pas la même clé.
 *
 * - `viewAny` → `dashboard.view` : ouvrir son propre tableau de bord. Il ne
 *   décide de rien d'autre — ce qu'on y voit dépend des rôles et de leurs
 *   permissions, et cette porte-là ne les remplace pas.
 * - `configure` → `dashboard.configure` : lire le catalogue des widgets, qui
 *   n'intéresse que qui règle un rôle.
 *
 * **Le réglage d'un rôle précis relève de `RolePolicy::configureDashboard`**,
 * et non d'ici : il ne suffit pas d'avoir la permission, encore faut-il que le
 * rôle appartienne à l'organisation de l'appelant et soit de portée
 * organisation. Ces deux conditions se vérifient sur le rôle, donc là où il est.
 */
class DashboardPolicy extends BaseOrganizationPolicy
{
    public function viewAny(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'dashboard.view');
    }

    public function configure(User $user, ?string $organizationId = null): bool
    {
        return $this->hasPermission($user, $organizationId, 'dashboard.configure');
    }
}
