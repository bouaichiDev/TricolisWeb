<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CustomerLoginRequest;
use App\Http\Resources\Api\V1\Customer\CustomerContextResource;
use App\Modules\CustomerPortal\Actions\LoginCustomerUser;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPermissions;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Support\CustomerPortalToken;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authentification du portail client.
 *
 * Le jeton rendu est un jeton **de portail** : il porte le Customer choisi à
 * la connexion et n'ouvre aucune route interne.
 */
class CustomerAuthController extends Controller
{
    public function __construct(
        private readonly CurrentCustomerContext $context,
        private readonly CustomerPortalAudit $audit,
    ) {}

    /**
     * Se connecter au portail client.
     *
     * Endpoint public, limité à 5 tentatives par minute et par couple IP/email.
     * Répond `409 customer_selection_required` si le compte est membre de
     * plusieurs clients et que `customerId` n'est pas fourni.
     */
    public function login(CustomerLoginRequest $request, LoginCustomerUser $login): JsonResponse
    {
        $data = $request->validated();
        $membership = $login->execute($request, $data['email'], $data['password'], $data['customerId'] ?? null);
        $membership->load(['user', 'customer']);

        // Un jeton du portail expire (Phase 7) : volé, il ne sert pas
        // indéfiniment. Sanctum refuse lui-même un jeton échu (401).
        $token = $membership->user->createToken(
            CustomerPortalToken::NAME,
            CustomerPortalToken::abilitiesFor($membership->customer_id),
            CustomerPortalToken::expiresAt(),
        )->plainTextToken;

        $this->context->set($membership, CustomerPermissions::codesFor($membership));

        return ApiResponse::ok([
            'token' => $token,
            'context' => new CustomerContextResource($this->context),
        ]);
    }

    /**
     * Se déconnecter : seul le jeton de la requête est révoqué.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->audit->record(
            $this->context->customer(),
            $this->context->user(),
            'logout',
            $this->context->membership(),
            null,
            [],
            $request->ip(),
        );

        $request->user()->currentAccessToken()->delete();

        return ApiResponse::noContent();
    }

    /**
     * Le compte connecté, son client, ses rôles et permissions effectives.
     */
    public function me(): JsonResponse
    {
        return ApiResponse::ok(new CustomerContextResource($this->context));
    }
}
