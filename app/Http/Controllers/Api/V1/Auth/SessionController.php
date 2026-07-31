<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sessions (jetons Sanctum) de l'utilisateur connecté.
 */
class SessionController extends Controller
{
    use AuditsCurrentUser;

    /**
     * Lister ses sessions actives.
     *
     * Seules les métadonnées sont exposées : identifiant, nom de l'appareil et
     * dates. Le jeton lui-même n'est jamais renvoyé après sa création.
     *
     * @response array{data: array{sessions: array<int, array{id: int, name: string, last_used_at: string|null, created_at: string}>}, meta: array{}}
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = $request->user()->tokens()->get(['id', 'name', 'last_used_at', 'created_at']);

        return ApiResponse::ok(['sessions' => $sessions]);
    }

    /**
     * Révoquer une session précise.
     *
     * L'identifiant doit appartenir à l'utilisateur connecté, sinon la requête
     * est rejetée en 422. L'opération est auditée.
     *
     * @response 204
     */
    public function destroy(Request $request, string $tokenId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        validator(['tokenId' => $tokenId], [
            'tokenId' => ['required', Rule::exists('personal_access_tokens', 'id')->where('tokenable_id', $user->id)],
        ])->validate();

        $user->tokens()->where('id', $tokenId)->delete();
        $this->auditForUser($request, 'session_revoked', null, ['session_id' => $tokenId]);

        return ApiResponse::noContent();
    }
}
