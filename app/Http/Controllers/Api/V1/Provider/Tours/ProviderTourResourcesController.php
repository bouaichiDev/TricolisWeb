<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Tours;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\Tours\AssignTourResourcesRequest;
use App\Http\Resources\Api\V1\Provider\ProviderTourResource;
use App\Modules\ProviderPortal\Actions\AssignProviderTourResources;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\AuditContext;
use Illuminate\Http\JsonResponse;

/**
 * Affecter chauffeur et véhicule à une tournée attribuée au fournisseur.
 * Permission : `provider.tours.assign-resources`.
 */
class ProviderTourResourcesController extends Controller
{
    public function __invoke(AssignTourResourcesRequest $request, string $tour, AssignProviderTourResources $assign, CurrentProviderContext $context): JsonResponse
    {
        $updated = $assign->execute(
            $tour,
            $request->validated(),
            new AuditContext($context->organizationId(), $context->user(), $request->ip()),
        );

        return ApiResponse::ok(new ProviderTourResource($updated->load(['agency:id,name', 'depot:id,name', 'driver:id,code,name', 'vehicle:id,code,registration_number'])));
    }
}
