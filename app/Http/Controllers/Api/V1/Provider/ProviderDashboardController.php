<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderDashboardComposer;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Tableau de bord du portail fournisseur. Permission : `provider.dashboard.view`.
 */
class ProviderDashboardController extends Controller
{
    public function __invoke(CurrentProviderContext $context, ProviderDashboardComposer $composer): JsonResponse
    {
        return ApiResponse::ok($composer->compose($context));
    }
}
