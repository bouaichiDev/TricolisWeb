<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\CustomerPortal\Support\CustomerPortalToken;
use App\Shared\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme l'administration interne aux jetons du portail client.
 *
 * Sans lui, un client dont le compte est aussi membre d'un transporteur
 * atteindrait l'interne avec son jeton de portail — et même sans appartenance,
 * les routes hors organisation (menu, statuts, notifications) lui
 * répondraient.
 */
final readonly class RejectCustomerPortalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (CustomerPortalToken::isPortalToken($request->user()?->currentAccessToken())) {
            return ApiResponse::error(
                'Ce jeton est réservé au portail client.',
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
