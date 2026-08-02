<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\ListPermissionRequest;
use App\Http\Resources\Api\V1\Identity\PermissionResource;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel global des permissions, en lecture seule.
 *
 * Les codes de permission forment le contrat entre l'API et les Policies : une
 * permission créée à l'exécution ne serait vérifiée par aucun code et resterait
 * inerte, tandis qu'en supprimer une ouvrirait silencieusement un accès. Le
 * référentiel est donc alimenté par `PermissionSeeder` et versionné avec le
 * code. Ce qui se pilote à l'exécution, ce sont les rôles
 * (`/api/v1/roles`) et leurs permissions.
 */
class PermissionController extends Controller
{
    /**
     * Lister les permissions disponibles.
     *
     * Permission requise : `roles.view`. Le référentiel est global : il ne
     * dépend pas de l'organisation active. Recherche sur le code et le libellé,
     * filtre `module`.
     */
    public function index(ListPermissionRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Role::class, $organizationId]);

        $query = Permission::query();

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }

        if ($request->filled('module')) {
            $query->where('module', $request->validated('module'));
        }

        $permissions = $query->orderBy('module')->orderBy('action')->get();

        return ApiResponse::ok(PermissionResource::collection($permissions));
    }

    /**
     * Consulter une permission.
     *
     * Permission requise : `roles.view`.
     */
    public function show(Request $request, Permission $permission): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Role::class, $organizationId]);

        return ApiResponse::ok(new PermissionResource($permission));
    }
}
