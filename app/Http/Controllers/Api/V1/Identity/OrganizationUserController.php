<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\StoreOrganizationUserRequest;
use App\Http\Requests\Api\V1\Identity\UpdateOrganizationUserRequest;
use App\Http\Resources\Api\V1\Identity\OrganizationUserResource;
use App\Modules\Identity\Actions\CreateOrganizationMember;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\RoleAssignmentGuard;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rattachements utilisateur↔organisation. Les rôles sont portés par le
 * rattachement `OrganizationUser`, jamais par l'utilisateur global.
 */
class OrganizationUserController extends Controller
{
    public function __construct(private readonly RoleAssignmentGuard $roleGuard) {}

    /**
     * Lister les membres de l'organisation active.
     *
     * Permission requise : `users.view`. Recherche sur l'email, le nom et le prénom.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('viewAny', [OrganizationUser::class, $org]);
        // `driver` est borne a l'organisation : un meme compte peut conduire
        // dans deux organisations, et la relation se fait par l'utilisateur.
        $query = OrganizationUser::where('organization_id', $org)
            ->with(['user', 'roles', 'driver' => fn ($driver) => $driver->where('organization_id', $org)]);
        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->whereHas('user', fn ($q) => $q->where('email', 'like', "%$search%")->orWhere('first_name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%"));
        }

        return ApiResponse::paginated($query->paginate($request->getPerPage())->through(fn ($item) => new OrganizationUserResource($item)));
    }

    /**
     * Créer un utilisateur et son rattachement à l'organisation active.
     *
     * Permissions requises : `users.create`, et `users.assign_roles` pour fournir
     * `roleIds`. Les rôles doivent appartenir à l'organisation active, sinon 422.
     */
    public function store(StoreOrganizationUserRequest $request, CreateOrganizationMember $createMember): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('create', [OrganizationUser::class, $org]);

        $data = $request->validated();
        $this->roleGuard->assertAssignable($request->user(), $org, $data['roleIds'] ?? []);

        $membership = $createMember->execute($data, $org);
        $this->audit($request, $org, 'created', $membership, null, $membership->load('roles')->toArray());

        return ApiResponse::created(new OrganizationUserResource($membership->load(['user', 'roles', 'driver' => fn ($driver) => $driver->where('organization_id', $membership->organization_id)])));
    }

    /**
     * Consulter un rattachement.
     *
     * Permission requise : `users.view`.
     */
    public function show(OrganizationUser $organizationUser): JsonResponse
    {
        $this->authorize('view', $organizationUser);

        return ApiResponse::ok(new OrganizationUserResource($organizationUser->load(['user', 'roles', 'driver' => fn ($driver) => $driver->where('organization_id', $organizationUser->organization_id)])));
    }

    /**
     * Modifier un rattachement et ses rôles.
     *
     * Permissions requises : `users.update`, et `users.assign_roles` pour
     * modifier `roleIds`. L'attribution et le retrait de rôle sont audités.
     */
    public function update(UpdateOrganizationUserRequest $request, OrganizationUser $organizationUser): JsonResponse
    {
        $this->authorize('update', $organizationUser);
        $oldValues = $organizationUser->load('roles')->toArray();
        $data = $request->validated();
        $this->roleGuard->assertAssignable($request->user(), $organizationUser->organization_id, $data['roleIds'] ?? []);
        DB::transaction(function () use ($data, $organizationUser) {
            $organizationUser->user->update(array_filter(['first_name' => $data['firstName'] ?? null, 'last_name' => $data['lastName'] ?? null, 'phone' => $data['phone'] ?? null, 'preferred_language' => $data['preferredLanguage'] ?? null], fn ($value) => $value !== null));
            $organizationUser->update(array_filter(['is_owner' => $data['isOwner'] ?? null, 'is_primary' => $data['isPrimary'] ?? null, 'status' => $data['status'] ?? null], fn ($value) => $value !== null));
            if (array_key_exists('roleIds', $data)) {
                $this->syncRoles($organizationUser, $data['roleIds']);
            }
        });
        $this->audit($request, $organizationUser->organization_id, 'updated', $organizationUser, $oldValues, $organizationUser->fresh()->load('roles')->toArray());

        return ApiResponse::ok(new OrganizationUserResource($organizationUser->fresh()->load(['user', 'roles', 'driver' => fn ($driver) => $driver->where('organization_id', $organizationUser->organization_id)])));
    }

    /**
     * Désactiver un rattachement.
     *
     * Permission requise : `users.disable`. Le rattachement passe au statut
     * `disabled` : il n'est jamais supprimé, afin de préserver l'historique.
     *
     * @response 204
     */
    public function destroy(Request $request, OrganizationUser $organizationUser): JsonResponse
    {
        $this->authorize('delete', $organizationUser);
        $oldValues = $organizationUser->toArray();
        $organizationUser->update(['status' => 'disabled']);
        $this->audit($request, $organizationUser->organization_id, 'status_changed', $organizationUser, $oldValues, $organizationUser->fresh()->toArray());

        return ApiResponse::noContent();
    }

    private function syncRoles(OrganizationUser $membership, array $roleIds): void
    {
        UserRole::where('organization_user_id', $membership->id)->whereNotIn('role_id', $roleIds)->delete();
        foreach ($roleIds as $roleId) {
            UserRole::firstOrCreate(['organization_user_id' => $membership->id, 'role_id' => $roleId]);
        }
    }
}
