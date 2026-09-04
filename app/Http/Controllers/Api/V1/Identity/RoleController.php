<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\StoreRoleRequest;
use App\Http\Requests\Api\V1\Identity\UpdateRoleRequest;
use App\Http\Resources\Api\V1\Identity\RoleResource;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Services\PlatformAccess;
use App\Shared\Enums\RoleScope;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Rôles d'une organisation et leurs permissions
 * (`Organization 1 — 0..* Role`, `Role 1 — 0..* RolePermission`).
 *
 * Deux invariants gouvernent ce contrôleur, et ils ne dépendent jamais de ce que
 * le client envoie :
 *
 * 1. **la portée et le drapeau système sont imposés**, jamais lus dans la
 *    requête. Un administrateur d'organisme qui poste `scope: platform` ou
 *    `isSystem: true` obtient un rôle local ordinaire, pas une élévation ;
 * 2. **les permissions demandées sont confrontées au plafond de délégation**.
 *    Nul n'accorde plus de droits qu'il n'en détient.
 */
class RoleController extends Controller
{
    public function __construct(private readonly PlatformAccess $platform) {}

    /**
     * Lister les rôles de l'organisation active.
     *
     * Permission requise : `roles.view`. Chaque rôle est renvoyé avec ses
     * permissions. Les rôles plateforme n'ont pas d'organisation : ils sont donc
     * absents de cette liste, sauf pour un administrateur plateforme.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('viewAny', [Role::class, $org]);

        $query = Role::with('permissions');

        if ($this->platform->isPlatformAdmin($request->user())) {
            $query->where(fn ($builder) => $builder->where('organization_id', $org)->orWhereNull('organization_id'));
        } else {
            $query->where('organization_id', $org);
        }

        return ApiResponse::paginated(
            $query->paginate($request->getPerPage())->through(fn ($role) => new RoleResource($role))
        );
    }

    /**
     * Créer un rôle.
     *
     * Permissions requises : `roles.create`, et `roles.assign_permissions` pour
     * fournir `permissionIds`. Le code doit être unique dans l'organisation.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('create', [Role::class, $org]);

        $data = $request->validated();
        validator($data, ['code' => [Rule::unique('roles')->where('organization_id', $org)]])->validate();

        $permissionIds = $data['permissionIds'] ?? [];
        $this->guardDelegation($request, $org, $permissionIds);

        $role = Role::create([
            'organization_id' => $org,
            'code' => $data['code'],
            'name' => $data['name'],
            // Imposées, jamais reprises de la requête : un rôle créé depuis
            // l'administration d'un organisme est local et non système.
            'scope' => RoleScope::ORGANIZATION->value,
            'is_system' => false,
            'status' => $data['status'],
        ]);

        $this->syncPermissions($role, $permissionIds);
        $this->audit($request, $org, 'created', $role, null, $role->load('permissions')->toArray());

        return ApiResponse::created(new RoleResource($role->load('permissions')));
    }

    /**
     * Consulter un rôle.
     *
     * Permission requise : `roles.view`.
     */
    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return ApiResponse::ok(new RoleResource($role->load('permissions')));
    }

    /**
     * Modifier un rôle et ses permissions.
     *
     * Permissions requises : `roles.update`, et `roles.assign_permissions` pour
     * modifier `permissionIds`. La portée et le drapeau système ne sont pas
     * modifiables : un rôle local ne devient jamais plateforme.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $oldValues = $role->load('permissions')->toArray();
        $data = $request->validated();

        if (array_key_exists('permissionIds', $data)) {
            $this->guardDelegation($request, $role->organization_id, $data['permissionIds']);
        }

        $role->update(array_filter(
            ['name' => $data['name'] ?? null, 'status' => $data['status'] ?? null],
            fn ($value) => $value !== null
        ));

        if (array_key_exists('permissionIds', $data)) {
            $this->syncPermissions($role, $data['permissionIds']);
        }

        $this->audit($request, $role->organization_id, 'updated', $role, $oldValues, $role->load('permissions')->toArray());

        return ApiResponse::ok(new RoleResource($role->load('permissions')));
    }

    /**
     * Supprimer un rôle.
     *
     * Permission requise : `roles.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('delete', $role);
        $oldValues = $role->load('permissions')->toArray();
        $this->audit($request, $role->organization_id, 'deleted', $role, $oldValues, null);
        $role->delete();

        return ApiResponse::noContent();
    }

    /**
     * Plafond de délégation.
     *
     * Chaque permission demandée est confrontée à celles que l'auteur peut
     * accorder. Accepter `permissionIds` sans cette vérification laisserait un
     * administrateur d'organisme se fabriquer un rôle plus puissant que le sien,
     * puis se l'attribuer.
     *
     * Le refus est un 422 portant la clé `permissionIds`, comme toute autre
     * donnée invalide : la requête est recevable, son contenu ne l'est pas.
     *
     * @param  array<int, string>  $permissionIds
     */
    private function guardDelegation(Request $request, ?string $organizationId, array $permissionIds): void
    {
        if ($permissionIds === []) {
            return;
        }

        $allowed = $this->platform->delegablePermissionCodes($request->user(), $organizationId);
        $requested = Permission::whereIn('id', $permissionIds)->pluck('code')->all();
        $refused = array_values(array_diff($requested, $allowed));

        if ($refused !== []) {
            throw ValidationException::withMessages([
                'permissionIds' => ['Vous ne pouvez pas accorder les permissions suivantes : '.implode(', ', $refused).'.'],
            ]);
        }
    }

    /**
     * @param  array<int, string>  $permissionIds
     */
    private function syncPermissions(Role $role, array $permissionIds): void
    {
        RolePermission::where('role_id', $role->id)->whereNotIn('permission_id', $permissionIds)->delete();

        foreach ($permissionIds as $permissionId) {
            RolePermission::firstOrCreate(['role_id' => $role->id, 'permission_id' => $permissionId]);
        }
    }
}
