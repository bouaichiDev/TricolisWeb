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
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rôles d'une organisation et leurs permissions
 * (`Organization 1 — 0..* Role`, `Role 1 — 0..* RolePermission`).
 */
class RoleController extends Controller
{
    /**
     * Lister les rôles de l'organisation active.
     *
     * Permission requise : `roles.view`. Chaque rôle est renvoyé avec ses permissions.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('viewAny', [Role::class, $org]);

        return ApiResponse::paginated(Role::where('organization_id', $org)->with('permissions')->paginate($request->getPerPage())->through(fn ($role) => new RoleResource($role)));
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
        $role = Role::create(['organization_id' => $org, 'code' => $data['code'], 'name' => $data['name'], 'scope' => $data['scope'] ?? null, 'is_system' => $data['isSystem'], 'status' => $data['status']]);
        $this->syncPermissions($role, $data['permissionIds'] ?? []);
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
     * modifier `permissionIds`.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);
        $oldValues = $role->load('permissions')->toArray();
        $data = $request->validated();
        $role->update(array_filter(['name' => $data['name'] ?? null, 'scope' => $data['scope'] ?? null, 'status' => $data['status'] ?? null], fn ($value) => $value !== null));
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

    private function syncPermissions(Role $role, array $permissionIds): void
    {
        RolePermission::where('role_id', $role->id)->whereNotIn('permission_id', $permissionIds)->delete();
        foreach ($permissionIds as $permissionId) {
            RolePermission::firstOrCreate(['role_id' => $role->id, 'permission_id' => $permissionId]);
        }
    }
}
