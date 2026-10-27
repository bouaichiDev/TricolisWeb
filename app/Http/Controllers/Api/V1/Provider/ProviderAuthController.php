<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\ProviderLoginRequest;
use App\Http\Resources\Api\V1\Provider\ProviderContextResource;
use App\Modules\ProviderPortal\Actions\LoginProviderUser;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderPermissions;
use App\Modules\ProviderPortal\Services\ProviderPortalAudit;
use App\Modules\ProviderPortal\Support\ProviderPortalToken;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authentification du portail fournisseur.
 *
 * Le jeton rendu est un jeton **de portail fournisseur** : il porte le
 * fournisseur choisi à la connexion, expire, et n'ouvre ni l'interne ni le
 * portail client.
 */
class ProviderAuthController extends Controller
{
    public function __construct(
        private readonly CurrentProviderContext $context,
        private readonly ProviderPortalAudit $audit,
    ) {}

    /**
     * Se connecter au portail fournisseur.
     *
     * Endpoint public, limité à 5 tentatives par minute et par couple IP/email.
     * Répond `409 provider_selection_required` si le compte est rattaché à
     * plusieurs fournisseurs et que `providerId` n'est pas fourni.
     */
    public function login(ProviderLoginRequest $request, LoginProviderUser $login): JsonResponse
    {
        $data = $request->validated();
        $membership = $login->execute($request, $data['email'], $data['password'], $data['providerId'] ?? null);
        $membership->load(['user', 'provider']);

        $token = $membership->user->createToken(
            ProviderPortalToken::NAME,
            ProviderPortalToken::abilitiesFor($membership->provider_id),
            ProviderPortalToken::expiresAt(),
        )->plainTextToken;

        $this->context->set($membership, ProviderPermissions::codesFor($membership));

        return ApiResponse::ok([
            'token' => $token,
            'context' => new ProviderContextResource($this->context),
        ]);
    }

    /**
     * Se déconnecter : seul le jeton de la requête est révoqué.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->audit->record($this->context->provider(), $this->context->user(), 'logout', $this->context->membership(), null, [], $request->ip());
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::noContent();
    }

    /**
     * Le compte connecté, son fournisseur, ses rôles et permissions effectives.
     */
    public function me(): JsonResponse
    {
        return ApiResponse::ok(new ProviderContextResource($this->context));
    }
}
