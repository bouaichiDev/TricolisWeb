<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\ActivateCustomerAccountRequest;
use App\Modules\ProviderPortal\Actions\ActivateProviderInvitation;
use App\Modules\ProviderPortal\Services\ProviderInvitationLookup;
use App\Shared\Enums\UserStatus;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Activation d'un compte invité au portail fournisseur.
 */
class ProviderActivationController extends Controller
{
    /**
     * Lire une invitation avant de l'accepter : l'écran sait s'il doit
     * demander un mot de passe. Rien n'est rendu sans le jeton.
     */
    public function show(Request $request, ProviderInvitationLookup $lookup): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'max:255']]);

        $invitation = $lookup->valid((string) $request->query('token'));
        $user = $invitation->providerUser->user;

        return ApiResponse::ok([
            'email' => $invitation->email,
            'providerName' => $invitation->provider->name,
            'carrierName' => $invitation->provider->organization?->name,
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'requiresPassword' => $user->status === UserStatus::INVITED,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
        ]);
    }

    /**
     * Accepter l'invitation. Le compte se connecte ensuite normalement.
     */
    public function activate(ActivateCustomerAccountRequest $request, ActivateProviderInvitation $activate): JsonResponse
    {
        $membership = $activate->execute($request->validated(), $request->ip());

        return ApiResponse::ok([
            'message' => 'Votre compte est activé. Vous pouvez vous connecter.',
            'email' => $membership->user->email,
        ]);
    }
}
