<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Dashboard\ShowDashboardRequest;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerDashboardComposer;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Tableau de bord du portail client, composé par le moteur de widgets
 * existant et configuré par le transporteur sur les rôles `CUSTOMER_*`.
 *
 * Aucune permission propre : chaque widget exige la sienne, et un widget
 * refusé n'est ni calculé ni renvoyé.
 */
class CustomerDashboardController extends Controller
{
    public function __invoke(ShowDashboardRequest $request, CustomerDashboardComposer $composer, CurrentCustomerContext $context): JsonResponse
    {
        $period = $request->period();

        return ApiResponse::ok([
            'customer' => ['id' => $context->customerId(), 'name' => $context->customer()->name],
            'period' => $period === null ? null : ['from' => $request->query('from'), 'to' => $request->query('to')],
            'widgets' => $composer->compose($period),
        ]);
    }
}
