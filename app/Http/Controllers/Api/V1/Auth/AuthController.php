<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Http\Resources\Api\V1\Organizations\OrganizationResource;
use App\Modules\Identity\Actions\LoginUser;
use App\Modules\Identity\Actions\RegisterTransporter;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authentification API par jetons Laravel Sanctum.
 */
class AuthController extends Controller
{
    use AuditsCurrentUser;

    /** @var list<string> */
    private const array CONTEXT_RELATIONS = ['organizationUsers.organization.agencies', 'organizationUsers.roles.permissions'];

    public function __construct(
        private readonly LoginUser $loginUser,
        private readonly RegisterTransporter $registerTransporter,
    ) {}

    /**
     * Inscrire un transporteur et son organisation.
     *
     * Endpoint public. Crée l'utilisateur, son organisation et le rattachement
     * propriétaire dans une seule transaction, puis renvoie un jeton d'accès.
     *
     * @response 201 array{data: array{user: UserResource, organization: OrganizationResource, token: string}, meta: array{}}
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $deviceName = $data['deviceName'] ?? $request->userAgent() ?? 'api';
        $registration = $this->registerTransporter->execute($data, $deviceName);
        $this->audit($request, $registration['organization']->id, 'registered', $registration['user'], null, $registration['user']->toArray());

        return ApiResponse::created([
            'user' => new UserResource($registration['user']),
            'organization' => new OrganizationResource($registration['organization']),
            'token' => $registration['token'],
        ]);
    }

    /**
     * Se connecter.
     *
     * Endpoint public, limité à 5 tentatives par minute et par couple IP/email.
     * Un compte suspendu ou désactivé est refusé. La réponse contient les
     * organisations, rôles, permissions et agences accessibles.
     *
     * @response array{data: array{user: UserResource, token: string}, meta: array{}}
     * @response 422 array{message: string, errors: array{email: array<int, string>}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $deviceName = $credentials['device_name'] ?? $request->userAgent() ?? 'api';

        $user = $this->loginUser->execute($request, $credentials);
        $user->load(self::CONTEXT_RELATIONS);

        return ApiResponse::ok([
            'user' => new UserResource($user),
            'token' => $user->createToken($deviceName)->plainTextToken,
        ]);
    }

    /**
     * Se déconnecter de la session courante.
     *
     * Seul le jeton utilisé pour la requête est révoqué. L'opération est auditée.
     *
     * @response 204
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auditForUser($request, 'logout');
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::noContent();
    }

    /**
     * Se déconnecter de toutes les sessions.
     *
     * Révoque l'ensemble des jetons de l'utilisateur. L'opération est auditée.
     *
     * @response 204
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $this->auditForUser($request, 'logout_all');
        $request->user()->tokens()->delete();

        return ApiResponse::noContent();
    }

    /**
     * Récupérer le contexte de sécurité de l'utilisateur connecté.
     *
     * Renvoie l'utilisateur, ses organisations, ses rattachements, ses rôles,
     * ses permissions et ses agences. Le hash de mot de passe n'est jamais exposé.
     *
     * @response array{data: array{user: UserResource}, meta: array{}}
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::ok([
            'user' => new UserResource($request->user()->load(self::CONTEXT_RELATIONS)),
        ]);
    }
}
