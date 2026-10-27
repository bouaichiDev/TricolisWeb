<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Shared\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `provider.permission:provider.dashboard.view` — toutes les permissions
 * listées sont exigées. Se place après `provider.portal`, qui a résolu le
 * contexte.
 */
final readonly class RequireProviderPermission
{
    public function __construct(private CurrentProviderContext $context) {}

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
