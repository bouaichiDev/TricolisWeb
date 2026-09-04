<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\UpdateRoleMenuRequest;
use App\Modules\Identity\Actions\SaveRoleMenuGroupSettings;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleMenuGroup;
use App\Modules\Identity\Models\RoleMenuItem;
use App\Modules\Identity\Services\RoleMenuCatalogue;
use App\Shared\Enums\RoleScope;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Menu\MenuCatalogue;
use App\Shared\Menu\MenuCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Menu d'un rôle.
 *
 * **C'est le seul endroit où le menu se règle.** Il se réglait auparavant à
 * deux niveaux — l'organisation pour l'ordre et les noms, le rôle pour la seule
 * visibilité — et il fallait savoir lequel ouvrir pour obtenir quoi. Chaque
 * rôle porte désormais son menu entier.
 *
 * Ce que cela implique, dit franchement : deux rôles peuvent nommer et ordonner
 * la même entrée différemment. Un utilisateur qui les cumule reçoit la
 * présentation de celui dont le code vient en premier, et la visibilité de
 * tous, par union — `UserRoleMenus` tient cette règle.
 *
 * Permission requise : `roles.update`. Régler le menu d'un rôle, c'est régler
 * le rôle.
 */
class RoleMenuController extends Controller
{
    public function __construct(
        private readonly RoleMenuCatalogue $menu,
        private readonly SaveRoleMenuGroupSettings $groups,
    ) {}

    /**
     * Catalogue configurable, avec l'état choisi par ce rôle.
     *
     * **Lire demande `view`, pas `update`.** Un rôle système ou plateforme ne se
     * modifie pas — et exiger `update` pour le lire rendait son menu
     * inaffichable : la fiche du rôle `admin` montrait « This action is
     * unauthorized » là où elle devait montrer, en lecture seule, le menu que ce
     * rôle donne à ses porteurs.
     *
     * Non filtré par les permissions du rôle. Ce serait tentant — pourquoi
     * proposer de masquer une entrée qu'il ne peut pas ouvrir ? — mais les deux
     * réglages se modifient séparément : une permission accordée demain
     * rendrait visible une entrée que personne n'aurait jamais eu l'occasion de
     * régler.
     */
    public function index(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return ApiResponse::ok($this->menu->forRole($role));
    }

    /**
     * Régler la visibilité, l'ordre, le libellé, l'icône et le rattachement.
     *
     * `alwaysVisible` ne protège plus qu'une entrée : **« Mon organisation »**.
     * Le groupe « Administration » l'était aussi, ce qui avait un sens quand le
     * réglage valait pour l'organisation entière ; un rôle « Bureau » qui n'a
     * que faire de l'administration doit pouvoir la ranger. Une seule entrée
     * suffit à garder un pied dans l'administration.
     *
     * La demande est **ignorée** plutôt que refusée : la requête reste valide,
     * c'est la contrainte qui l'emporte. Et masquer n'est de toute façon pas
     * interdire — l'écran reste atteignable par son adresse.
     *
     * L'autorisation est `updateMenu`, pas `update` : régler le menu du rôle
     * `admin` n'est pas modifier ses permissions.
     */
    public function update(UpdateRoleMenuRequest $request, Role $role): JsonResponse
    {
        $this->authorize('updateMenu', $role);

        $items = $request->validated('items');
        $customGroupCodes = MenuCodes::customGroups($role->id);
        $before = $this->menu->forRole($role);

        DB::transaction(function () use ($items, $role, $customGroupCodes): void {
            foreach ($items as $item) {
                if (RoleMenuGroup::isCustomCode($item['code'])) {
                    $this->groups->apply($role->id, $item);

                    continue;
                }

                $entry = MenuCatalogue::find($item['code']);

                if ($entry === null || $entry->scope !== RoleScope::ORGANIZATION) {
                    continue;
                }

                RoleMenuItem::updateOrCreate(
                    ['role_id' => $role->id, 'code' => $entry->code],
                    $this->valuesFor($entry, $item, $customGroupCodes)
                );
            }
        });

        $after = $this->menu->forRole($role);
        $this->auditMenu($request, $role, $before, $after);

        return ApiResponse::ok($after);
    }

    /**
     * Colonnes à écrire pour une entrée du catalogue.
     *
     * Absent de la requête n'est pas la même chose qu'envoyé vide : le premier
     * laisse le réglage en place, le second le retire. Sans cette distinction,
     * un client qui n'enverrait que les positions effacerait tous les libellés
     * choisis.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $customGroupCodes
     * @return array<string, mixed>
     */
    private function valuesFor(object $entry, array $item, array $customGroupCodes): array
    {
        $values = [
            'is_visible' => $entry->alwaysVisible ? true : ($item['isVisible'] ?? true),
            'position' => $item['position'] ?? $entry->position,
        ];

        foreach (['label', 'icon'] as $field) {
            if (array_key_exists($field, $item)) {
                $values[$field] = $this->blankToNull($item[$field]);
            }
        }

        // Le rattachement demande les deux colonnes ensemble : « au premier
        // niveau » s'écrit `null`, et l'écrire seul serait relu comme « aucun
        // choix ». Un déplacement que l'arbre ne supporte pas — un groupe qu'on
        // rangerait dans un groupe — est ignoré plutôt que refusé.
        if (array_key_exists('parent', $item)) {
            $parent = $this->blankToNull($item['parent']);

            if (MenuCatalogue::canReparent($entry, $parent, $customGroupCodes)) {
                $values['parent_overridden'] = $parent !== $entry->parent;
                $values['parent_code'] = $parent;
            }
        }

        return $values;
    }

    /**
     * Le rôle appartient à une organisation, et c'est son journal qui porte la
     * trace : un menu qui change pour tout un métier se constate le lendemain,
     * et il faut pouvoir dire qui l'a décidé.
     *
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $after
     */
    private function auditMenu(UpdateRoleMenuRequest $request, Role $role, array $before, array $after): void
    {
        if ($role->organization_id === null) {
            return;
        }

        $this->audit(
            $request,
            $role->organization_id,
            'role_menu_updated',
            $role,
            ['menu' => $before],
            ['menu' => $after],
        );
    }

    /**
     * Un champ vidé revient au catalogue.
     *
     * L'écran de réglage n'a pas de bouton « annuler mon libellé » : effacer le
     * champ **est** ce bouton. Enregistrer la chaîne vide telle quelle
     * afficherait une entrée sans nom, impossible à retrouver dans la barre
     * latérale.
     */
    private function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
