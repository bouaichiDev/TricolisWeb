<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Shared\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `customer.permission:customer.users.view` — toutes les permissions listées
 * sont exigées. Se place après `customer.portal`, qui a résolu le contexte.
 */
final readonly class RequireCustomerPermission
{
    public function __construct(private CurrentCustomerContext $context) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        foreach ($permissions as $permission) {
            if (! $this->context->can($permission)) {
                return ApiResponse::error(
                    'Vous n’avez pas la permission requise pour cette action.',
                    JsonResponse::HTTP_FORBIDDEN,
                );
            }
        }

        return $next($request);
    }
}
