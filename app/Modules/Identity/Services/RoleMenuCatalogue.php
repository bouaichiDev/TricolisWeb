<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Role;
use App\Shared\Enums\RoleScope;
use App\Shared\Menu\MenuCatalogue;
use App\Shared\Menu\MenuEntry;

/**
 * Le catalogue tel que l'écran de réglage d'un rôle l'affiche.
 *
 * Distinct de `MenuResolver`, qui compose le menu **servi** à quelqu'un : ici
 * on montre tout ce qui est réglable, là on montre ce qui est atteignable. Les
 * deux divergent sur trois points, et chacun a sa raison :
 *
 * - **les permissions ne filtrent pas** — les deux réglages se modifient
 *   séparément, et masquer les entrées que le rôle ne peut pas encore ouvrir
 *   empêcherait de préparer son menu avant de lui accorder le droit ;
 * - **les entrées masquées restent proposées**, décochées : les retirer
 *   rendrait le geste irréversible, faute d'endroit où les remontrer ;
 * - **les groupes vides restent** : c'est ici qu'on les remplit, et un groupe
 *   qui disparaîtrait aussitôt créé serait impossible à utiliser.
 *
 * Une seule entrée reste verrouillée, pour tous les rôles : « Mon
 * organisation ». Le catalogue le dit par `alwaysVisible`, et `canHide` le
 * répercute.
 */
final readonly class RoleMenuCatalogue
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forRole(Role $role): array
    {
        $groups = RoleMenuGroups::for($role->id);
        $chosen = RoleMenuOverrides::for($role->id, $groups->codes());
        $rows = $groups->byCode();

        $items = array_map(
            function (MenuEntry $entry) use ($chosen, $rows): array {
                $row = $rows[$entry->code] ?? null;

                return $entry->toArray(
                    $row?->is_visible ?? $chosen->isEnabled($entry),
                    $chosen->positionOf($entry),
                    $row?->label ?? $chosen->labelOf($entry),
                    $chosen->iconOf($entry),
                    $chosen->reparents($entry),
                    $chosen->parentOf($entry),
                );
            },
            [...MenuCatalogue::forScope(RoleScope::ORGANIZATION), ...$groups->entries()],
        );

        usort($items, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return $items;
    }
}
