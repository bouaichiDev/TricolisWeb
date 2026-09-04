<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\StoreRoleMenuGroupRequest;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleMenuGroup;
use App\Modules\Identity\Models\RoleMenuItem;
use App\Modules\Identity\Services\RoleMenuCatalogue;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Groupes de menu créés sur un rôle.
 *
 * **Pourquoi ceux-là naissent en base alors que le catalogue reste en code :**
 * un groupe n'ouvre rien. Ni route, ni permission — c'est un titre repliable
 * au-dessus d'entrées qui, elles, gardent leur destination du code. Les deux
 * raisons qui gardent le catalogue hors de la base — une route qui mènerait à
 * « Page introuvable », une permission qui ouvrirait un écran interdit — ne
 * s'appliquent donc pas ici. Créer un groupe est du rangement, pas du routage.
 *
 * Seules la naissance et la mort sont ici. Le nom, l'icône, le rang et la
 * visibilité d'un groupe se règlent par `PATCH /roles/{role}/menu`, avec tout
 * le reste : on ne compose pas un menu en deux enregistrements.
 *
 * Autorisation : `updateMenu` — régler le menu d'un rôle n'est pas modifier ses
 * permissions, et le rôle système est réglable comme les autres.
 */
class RoleMenuGroupController extends Controller
{
    public function __construct(private readonly RoleMenuCatalogue $menu) {}

    /**
     * Créer un groupe.
     *
     * Il naît **vide, et donc invisible** dans la barre latérale : un groupe
     * sans enfant y afficherait un titre qui n'ouvre rien. Il apparaît dès
     * qu'on y range une entrée, depuis le même écran — d'où sa présence dans le
     * réglage, qui ne retire pas les groupes vides.
     */
    public function store(StoreRoleMenuGroupRequest $request, Role $role): JsonResponse
    {
        $this->authorize('updateMenu', $role);

        $before = $this->menu->forRole($role);

        RoleMenuGroup::create([
            'role_id' => $role->id,
            'code' => RoleMenuGroup::newCode(),
            'label' => trim((string) $request->validated('label')),
            'icon' => $request->validated('icon'),
            'is_visible' => true,
            // Au bout de la liste : un groupe créé se remarque là où on vient
            // de le poser, et non glissé au milieu d'entrées qu'on n'a pas
            // touchées. Le premier enregistrement le renumérotera.
            'position' => 9999,
        ]);

        $after = $this->menu->forRole($role);
        $this->auditGroup($request, $role, 'role_menu_group_created', $before, $after);

        return ApiResponse::ok($after);
    }

    /**
     * Supprimer un groupe créé.
     *
     * **Ses entrées ne disparaissent pas avec lui** : elles retrouvent le
     * rattachement que leur donne le catalogue. Les supprimer avec le groupe
     * retirerait des écrans pour un geste de rangement ; les laisser pointer
     * vers un groupe absent laisserait en base une référence qui ne désigne
     * plus rien.
     *
     * Un groupe livré par le catalogue ne se supprime pas : il n'appartient pas
     * au rôle, et la recherche ne le trouve simplement pas.
     */
    public function destroy(Request $request, Role $role, string $code): JsonResponse
    {
        $this->authorize('updateMenu', $role);

        $group = RoleMenuGroup::where('role_id', $role->id)->where('code', $code)->firstOrFail();
        $before = $this->menu->forRole($role);

        DB::transaction(function () use ($group, $role): void {
            RoleMenuItem::where('role_id', $role->id)
                ->where('parent_code', $group->code)
                ->update(['parent_overridden' => false, 'parent_code' => null]);

            $group->delete();
        });

        $after = $this->menu->forRole($role);
        $this->auditGroup($request, $role, 'role_menu_group_deleted', $before, $after);

        return ApiResponse::ok($after);
    }

    /**
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $after
     */
    private function auditGroup(Request $request, Role $role, string $action, array $before, array $after): void
    {
        if ($role->organization_id === null) {
            return;
        }

        $this->audit($request, $role->organization_id, $action, $role, ['menu' => $before], ['menu' => $after]);
    }
}
