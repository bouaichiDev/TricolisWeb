<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PlatformAccess;
use App\Modules\Identity\Services\RoleMenuGroups;
use App\Modules\Identity\Services\RoleMenuOverrides;
use App\Modules\Identity\Services\UserRoleMenus;
use App\Policies\BaseOrganizationPolicy;
use App\Shared\Enums\RoleScope;
use App\Shared\Menu\MenuCatalogue;
use App\Shared\Menu\MenuEntry;

/**
 * Compose le menu effectif d'un utilisateur.
 *
 * **Le menu appartient au rôle.** Chaque rôle porte le sien en entier — ordre,
 * noms, icônes, groupes, visibilité — et se règle depuis sa propre fiche. Il
 * n'y a plus de réglage au niveau de l'organisation : deux endroits pour une
 * même chose obligeaient à savoir lequel ouvrir pour obtenir quoi.
 *
 * Trois filtres, dans cet ordre :
 *
 * 1. **la portée** — un compte plateforme reçoit le menu plateforme, pas le
 *    menu d'organisme expurgé : les clients et les agences appartiennent aux
 *    organismes. N'ayant pas de rôle d'organisation, il reçoit le catalogue tel
 *    qu'il est livré ;
 * 2. **les rôles de l'utilisateur** — ce que son métier voit, et comment.
 *    `UserRoleMenus` tient la règle : présentation du rôle principal,
 *    visibilité par union ;
 * 3. **les permissions de l'utilisateur** — une entrée dont il n'a pas le droit
 *    ne lui est pas proposée.
 *
 * Le dernier ne se négocie pas : c'est lui qui fait du menu la projection des
 * droits, et non l'inverse.
 *
 * Ce que l'écran de réglage affiche est autre chose, et vit ailleurs :
 * `RoleMenuCatalogue` montre tout ce qui est **réglable**, là où celui-ci
 * montre ce qui est **atteignable**.
 */
class MenuResolver extends BaseOrganizationPolicy
{
    public function __construct(private readonly PlatformAccess $platform) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolve(User $user, ?string $organizationId): array
    {
        if ($this->platform->isPlatformAdmin($user)) {
            return $this->compose($user, RoleScope::PLATFORM, $organizationId, UserRoleMenus::for($user->id, null));
        }

        return $this->compose(
            $user,
            RoleScope::ORGANIZATION,
            $organizationId,
            UserRoleMenus::for($user->id, $organizationId),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function compose(User $user, RoleScope $scope, ?string $organizationId, UserRoleMenus $roles): array
    {
        $primary = $roles->primary();
        $groups = RoleMenuGroups::for($primary?->id);
        $chosen = RoleMenuOverrides::for($primary?->id, $groups->codes());
        $rows = $groups->byCode();
        $hidden = $roles->hiddenByEveryRole();

        $visible = [];

        foreach ([...MenuCatalogue::forScope($scope), ...$groups->entries()] as $entry) {
            if (! $this->isVisible($entry, $rows[$entry->code] ?? null, $chosen, $roles, $hidden)) {
                continue;
            }

            if (! $this->isPermitted($user, $entry, $organizationId, $scope)) {
                continue;
            }

            $own = $rows[$entry->code] ?? null;

            $visible[$entry->code] = $entry->toArray(
                true,
                $chosen->positionOf($entry),
                $own?->label ?? $chosen->labelOf($entry),
                $chosen->iconOf($entry),
                $chosen->reparents($entry),
                $chosen->parentOf($entry),
            );
        }

        // Un groupe dont plus aucun enfant ne subsiste n'a rien à ouvrir : il
        // afficherait un titre vide, ce que le §10 interdit.
        foreach ($visible as $code => $item) {
            if ($item['route'] === null && ! $this->hasVisibleChild($code, $visible)) {
                unset($visible[$code]);
            }
        }

        return $this->sorted(array_values($visible));
    }

    /**
     * Une entrée figure-t-elle au menu de cet utilisateur ?
     *
     * Trois cas, dans cet ordre. Un **groupe créé** n'appartient qu'au rôle
     * principal : sa propre ligne décide, les autres rôles n'en savent rien.
     * **Sans autre rôle**, le principal décide seul. **Avec plusieurs**, c'est
     * l'union qui tranche — une entrée ne tombe que si tous la masquent.
     *
     * `alwaysVisible` court-circuite tout, et ne concerne plus qu'une entrée :
     * « Mon organisation ». Elle garde à chacun un pied dans l'administration,
     * quels que soient les rôles qu'il porte.
     *
     * @param  object|null  $own  Ligne du groupe créé, s'il s'agit d'un groupe créé.
     * @param  array<int, string>  $hidden
     */
    private function isVisible(
        MenuEntry $entry,
        ?object $own,
        RoleMenuOverrides $chosen,
        UserRoleMenus $roles,
        array $hidden,
    ): bool {
        if ($entry->alwaysVisible) {
            return true;
        }

        return match (true) {
            $own !== null => (bool) $own->is_visible,
            $roles->isEmpty() => $chosen->isEnabled($entry),
            default => ! in_array($entry->code, $hidden, true),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function sorted(array $items): array
    {
        usort($items, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return $items;
    }

    /**
     * Un groupe n'a pas de permission propre : il s'ouvre si un enfant s'ouvre.
     */
    private function isPermitted(User $user, MenuEntry $entry, ?string $organizationId, RoleScope $scope): bool
    {
        if ($entry->permission === null) {
            return true;
        }

        if ($scope === RoleScope::PLATFORM) {
            return true;
        }

        return $this->hasPermission($user, $organizationId, $entry->permission);
    }

    /**
     * @param  array<string, array<string, mixed>>  $visible
     */
    private function hasVisibleChild(string $code, array $visible): bool
    {
        foreach ($visible as $item) {
            if ($item['parent'] === $code) {
                return true;
            }
        }

        return false;
    }
}
