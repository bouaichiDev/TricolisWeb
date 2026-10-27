<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Providers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Providers\InviteProviderUserRequest;
use App\Http\Requests\Api\V1\Providers\UpdateProviderUserRequest;
use App\Http\Resources\Api\V1\Providers\ProviderPortalUserResource;
use App\Modules\Identity\Models\Role;
use App\Modules\ProviderPortal\Actions\AssignProviderUserRole;
use App\Modules\ProviderPortal\Actions\ChangeProviderUserStatus;
use App\Modules\ProviderPortal\Actions\InviteProviderUser;
use App\Modules\ProviderPortal\Actions\ResendProviderInvitation;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Support\ProviderPortalRoles;
use App\Modules\Providers\Models\Provider;
use App\Shared\Enums\RoleScope;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Accès au portail d'un fournisseur, gérés **uniquement** depuis le
 * back-office : le portail fournisseur n'administre ni comptes ni rôles.
 *
 * Lecture : `providers.view` ; écritures : `providers.update`, sur la fiche
 * du fournisseur. Un membre est toujours cherché **dans** le fournisseur de
 * l'URL : l'identifiant d'un membre d'un autre fournisseur répond 404.
 */
class ProviderPortalUserController extends Controller
{
    /**
     * Lister les accès au portail d'un fournisseur.
     */
    public function index(Provider $provider): JsonResponse
    {
        $this->authorize('view', $provider);

        $members = ProviderUser::forProvider($provider->id)->with(['user', 'roles'])->latest()->get();

        return ApiResponse::ok(ProviderPortalUserResource::collection($members));
    }

    /**
     * Les rôles attribuables au portail de ce fournisseur, sans exiger
     * `roles.view` : qui gère les accès doit pouvoir choisir le rôle.
     */
    public function roles(Provider $provider): JsonResponse
    {
        $this->authorize('view', $provider);

        $roles = $this->roleQuery($provider)->orderBy('name')->get(['id', 'code', 'name']);

        return ApiResponse::ok($roles->map(static fn (Role $role): array => ['id' => $role->id, 'code' => $role->code, 'name' => $role->name])->values());
    }

    /**
     * Inviter un compte ; rôle « Administrateur fournisseur » à défaut.
     */
    public function invite(InviteProviderUserRequest $request, Provider $provider, InviteProviderUser $invite): JsonResponse
    {
        $this->authorize('update', $provider);

        $data = $request->validated();
        $role = $this->role($provider, $data['roleId'] ?? null);
        $member = $invite->execute($provider, $data, $role, $request->user(), $request->ip());

        return ApiResponse::created(new ProviderPortalUserResource($member));
    }

    /**
     * Changer le rôle d'un membre.
     */
    public function update(UpdateProviderUserRequest $request, Provider $provider, string $providerUser, AssignProviderUserRole $assign): JsonResponse
    {
        $this->authorize('update', $provider);

        $member = $assign->execute($this->member($provider, $providerUser), $this->role($provider, $request->validated('roleId')), $request->user(), $request->ip());

        return ApiResponse::ok(new ProviderPortalUserResource($member));
    }

    public function disable(Request $request, Provider $provider, string $providerUser, ChangeProviderUserStatus $change): JsonResponse
    {
        $this->authorize('update', $provider);
        $member = $change->disable($this->member($provider, $providerUser), $request->user(), $request->ip());

        return ApiResponse::ok(new ProviderPortalUserResource($member->load(['user', 'roles'])));
    }

    public function enable(Request $request, Provider $provider, string $providerUser, ChangeProviderUserStatus $change): JsonResponse
    {
        $this->authorize('update', $provider);
        $member = $change->enable($this->member($provider, $providerUser), $request->user(), $request->ip());

        return ApiResponse::ok(new ProviderPortalUserResource($member->load(['user', 'roles'])));
    }

    public function resendInvitation(Request $request, Provider $provider, string $providerUser, ResendProviderInvitation $resend): JsonResponse
    {
        $this->authorize('update', $provider);
        $member = $resend->execute($this->member($provider, $providerUser), $request->user(), $request->ip());

        return ApiResponse::ok(new ProviderPortalUserResource($member->load(['user', 'roles'])));
    }

    private function member(Provider $provider, string $id): ProviderUser
    {
        return ProviderUser::forProvider($provider->id)->with(['user', 'provider', 'roles'])->findOrFail($id);
    }

    /**
     * Un rôle de portée `provider`, actif, de l'organisation du fournisseur.
     */
    private function role(Provider $provider, ?string $roleId): Role
    {
        $roles = $this->roleQuery($provider);

        $role = $roleId === null
            ? $roles->where('code', ProviderPortalRoles::ADMIN)->first()
            : $roles->whereKey($roleId)->first();

        return $role ?? throw ValidationException::withMessages(['roleId' => ['Ce rôle n’existe pas pour le portail fournisseur.']]);
    }

    /**
     * @return Builder<Role>
     */
    private function roleQuery(Provider $provider): Builder
    {
        return Role::where('organization_id', $provider->organization_id)
            ->where('scope', RoleScope::PROVIDER->value)
            ->where('status', 'active');
    }
}
