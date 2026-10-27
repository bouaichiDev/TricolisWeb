<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPermissions;
use App\Modules\CustomerPortal\Support\CustomerPortalToken;
use App\Modules\CustomerPortal\Support\PortalOrganizationGate;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chaîne de sécurité du portail client, dans l'ordre de la spécification :
 *
 * ```
 * Authentification → jeton portail → compte actif → membre actif
 *   → client actif → Customer Context
 * ```
 *
 * Chaque maillon est revérifié à **chaque requête** : désactiver un membre ou
 * un client coupe l'accès immédiatement, sans attendre l'expiration du jeton.
 *
 * Les refus portent le code `customer_access_revoked` : le portail sait alors
 * qu'il doit revenir à l'écran de connexion plutôt qu'afficher « accès refusé ».
 */
final readonly class EnsureCustomerPortalContext
{
    public const string REVOKED = 'customer_access_revoked';

    public function __construct(private CurrentCustomerContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $customerId = CustomerPortalToken::customerIdOf($user?->currentAccessToken());

        if ($user === null || $customerId === null) {
            return $this->deny('Ce jeton ne donne pas accès au portail client.');
        }

        if (! $user->status->allowsLogin()) {
            return $this->deny('Ce compte est inactif ou suspendu.');
        }

        $membership = CustomerUser::with(['customer', 'user'])
            ->where('customer_id', $customerId)
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null || ! $membership->isActive()) {
            return $this->deny('Votre accès au portail client est désactivé.');
        }

        if ($membership->customer->status !== CustomerStatus::ACTIVE) {
            return $this->deny('Le compte client est inactif.');
        }

        // L'organisation du transporteur, suspendue ou fermée, ferme le portail
        // de tous ses clients (Phase 7) — même message générique.
        if (! PortalOrganizationGate::allows($membership->customer)) {
            return $this->deny('Le compte client est inactif.');
        }

        $this->context->set($membership, CustomerPermissions::codesFor($membership));

        return $next($request);
    }

    private function deny(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => self::REVOKED], JsonResponse::HTTP_FORBIDDEN);
    }
}
