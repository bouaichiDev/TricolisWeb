<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Organizations\CurrentOrganizationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureOrganizationContext
{
    public function __construct(
        private CurrentOrganizationContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $organizationId = $this->context->getOrganizationId();
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($organizationId === null) {
            return ApiResponse::error(
                'L’en-tête X-Organization-Id est requis.',
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        if (! $this->context->hasOrganizationAccess()) {
            return ApiResponse::error(
                'Vous n’avez pas accès à cette organisation.',
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        $request->attributes->set('organization_id', $organizationId);
        $request->attributes->set('organization_user', $this->context->getOrganizationUser());

        return $next($request);
    }
}
