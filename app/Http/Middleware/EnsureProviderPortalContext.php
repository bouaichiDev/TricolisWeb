<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\CustomerPortal\Support\PortalOrganizationGate;
use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderPermissions;
use App\Modules\ProviderPortal\Support\ProviderPortalToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chaîne de sécurité du portail fournisseur :
 *
 * ```
 * Authentification → jeton portail fournisseur → compte actif
 *   → rattachement actif → fournisseur actif → organisation ouverte
 *   → `provider.portal.access` → Provider Context
 * ```
 *
 * Chaque maillon est revérifié à **chaque requête** : bloquer un fournisseur
 * ou désactiver un rattachement coupe l'accès immédiatement.
 *
 * « Fournisseur actif » = statut `active` du référentiel ; tout autre code
 * (`inactive`, `blocked`, ou un code ajouté plus tard) ferme le portail.
 *
 * Les refus portent le code `provider_access_revoked` et un message générique :
 * le portail revient à la connexion sans apprendre quel maillon a cédé.
 */
final readonly class EnsureProviderPortalContext
{
    public const string REVOKED = 'provider_access_revoked';

    public const string ACTIVE_PROVIDER = 'active';

    public function __construct(private CurrentProviderContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $providerId = ProviderPortalToken::providerIdOf($user?->currentAccessToken());

        if ($user === null || $providerId === null || ! $user->status->allowsLogin()) {
            return $this->deny();
        }

        $membership = ProviderUser::with(['provider', 'user'])
            ->where('provider_id', $providerId)
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null || ! $membership->isActive()
            || $membership->provider->status !== self::ACTIVE_PROVIDER
            || ! PortalOrganizationGate::allowsProvider($membership->provider)) {
            return $this->deny();
        }

        $permissions = ProviderPermissions::codesFor($membership);

        if (! in_array('provider.portal.access', $permissions, true)) {
            return $this->deny();
        }

        $this->context->set($membership, $permissions);

        return $next($request);
    }

    private function deny(): JsonResponse
    {
        return response()->json(
            ['message' => 'Votre accès au portail fournisseur est fermé.', 'code' => self::REVOKED],
            JsonResponse::HTTP_FORBIDDEN,
        );
    }
}
