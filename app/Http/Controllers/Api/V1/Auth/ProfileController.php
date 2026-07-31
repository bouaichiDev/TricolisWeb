<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Modules\Identity\Actions\UpdateUserProfile;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Profil et mot de passe de l'utilisateur connecté.
 */
class ProfileController extends Controller
{
    use AuditsCurrentUser;

    public function __construct(private readonly UpdateUserProfile $updateProfile) {}

    /**
     * Mettre à jour son profil.
     *
     * Aucune permission métier requise : l'utilisateur agit sur son propre compte.
     * L'opération est auditée avec les anciennes et nouvelles valeurs.
     *
     * @response array{data: array{user: UserResource}, meta: array{}}
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $changes = $this->updateProfile->execute($user, $request->validated());
        $this->auditForUser($request, 'profile_updated', $changes['old'], $changes['new']);

        return ApiResponse::ok(['user' => new UserResource($user->fresh())]);
    }

    /**
     * Changer son mot de passe.
     *
     * Le mot de passe courant est exigé. Toutes les sessions existantes sont
     * révoquées et un nouveau jeton est renvoyé. Le hash n'est jamais exposé ni journalisé.
     *
     * @response array{data: array{token: string}, meta: array{}}
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['password' => Hash::make($request->validated('password'))]);
        $this->auditForUser($request, 'password_changed');

        $user->tokens()->delete();
        $token = $user->createToken($request->userAgent() ?? 'api')->plainTextToken;

        return ApiResponse::ok(['token' => $token]);
    }
}
