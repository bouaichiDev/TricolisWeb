<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\StoreOrganizationUserRequest;
use App\Http\Requests\Api\V1\Identity\UpdateUserRequest;
use App\Http\Resources\Api\V1\Identity\UserResource;
use App\Modules\Identity\Actions\CreateOrganizationMember;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\RoleAssignmentGuard;
use App\Shared\Enums\UserStatus;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Annuaire des utilisateurs de l'organisation active.
 *
 * Un utilisateur n'existe pour l'API que s'il possède un rattachement dans
 * l'organisation active : la liste est donc naturellement isolée. Les rôles se
 * gèrent sur le rattachement, via `/api/v1/organization-users`.
 */
class UserController extends Controller
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'first_name' => 'firstName',
        'last_name' => 'lastName',
        'email' => 'email',
        'phone' => 'phone',
        'preferred_language' => 'preferredLanguage',
        'status' => 'status',
    ];

    /**
     * Lister les utilisateurs de l'organisation active.
     *
     * Permission requise : `users.view`. Recherche sur le nom, le prénom et
     * l'email. Tri autorisé sur `last_name`, `first_name`, `email`, `created_at`.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [User::class, $organizationId]);

        $query = User::whereHas('organizationUsers', fn ($builder) => $builder->where('organization_id', $organizationId))
            ->with(['organizationUsers' => fn ($builder) => $builder->where('organization_id', $organizationId)]);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->getSort('last_name', ['last_name', 'first_name', 'email', 'created_at']);
        $paginator = $query->orderBy($sort, $request->getDirection())->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (User $user) => new UserResource($user)));
    }

    /**
     * Créer un utilisateur et le rattacher à l'organisation active.
     *
     * Permissions requises : `users.create`, et `users.assign_roles` pour fournir
     * `roleIds`. L'utilisateur et son rattachement sont créés dans la même
     * transaction : un compte sans rattachement serait inatteignable.
     */
    public function store(StoreOrganizationUserRequest $request, CreateOrganizationMember $createMember, RoleAssignmentGuard $roleGuard): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [User::class, $organizationId]);

        $data = $request->validated();
        $roleGuard->assertAssignable($request->user(), $organizationId, $data['roleIds'] ?? []);

        $membership = $createMember->execute($data, $organizationId);
        $user = $membership->user;
        $this->audit($request, $organizationId, 'created', $user, null, $user->toArray());

        return ApiResponse::created(new UserResource($user->load('organizationUsers')));
    }

    /**
     * Consulter un utilisateur de l'organisation active.
     *
     * Permission requise : `users.view`. Un compte sans rattachement dans une
     * organisation partagée renvoie 403.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::ok(new UserResource($user->load('organizationUsers')));
    }

    /**
     * Modifier les informations d'un utilisateur.
     *
     * Permission requise : `users.update`. Le mot de passe ne se change pas ici :
     * il relève de `PATCH /api/v1/auth/password` ou de la réinitialisation.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $oldValues = $user->only(array_keys(self::MAPPING));
        $user->update(InputMapper::map($request->validated(), self::MAPPING));
        $this->auditUser($request, $user, 'updated', $oldValues);

        return ApiResponse::ok(new UserResource($user->fresh()->load('organizationUsers')));
    }

    /**
     * Désactiver un utilisateur.
     *
     * Permission requise : `users.disable`. Le compte n'est jamais supprimé —
     * il est référencé par l'audit et les documents — mais passe au statut
     * `disabled`, ce qui lui interdit toute connexion.
     *
     * @response 204
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('disable', $user);

        $oldValues = ['status' => $user->status?->value];
        $user->update(['status' => UserStatus::DISABLED]);
        $user->tokens()->delete();
        $this->auditUser($request, $user, 'status_changed', $oldValues);

        return ApiResponse::noContent();
    }

    /**
     * @param  array<string, mixed>  $oldValues
     */
    private function auditUser(Request $request, User $user, string $action, array $oldValues): void
    {
        $organizationId = $this->requireOrganizationId();
        $newValues = $user->fresh()->only(array_keys($oldValues));

        $this->audit($request, $organizationId, $action, $user, $oldValues, $newValues);
    }
}
