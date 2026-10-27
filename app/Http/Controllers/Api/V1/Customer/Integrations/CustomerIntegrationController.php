<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\Integrations\CustomerApiIntegrationResource;
use App\Http\Resources\Api\V1\Customer\Integrations\CustomerExportIntegrationResource;
use App\Http\Resources\Api\V1\Customer\Integrations\CustomerImportIntegrationResource;
use App\Modules\CustomerPortal\Services\Integrations\CustomerIntegrationActivity;
use App\Modules\CustomerPortal\Services\Integrations\CustomerIntegrationScope;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Les intégrations du client courant, en lecture : vue d'ensemble, imports,
 * exports, accès API. Rien ici n'expose une connexion ni un secret — le
 * client voit ses flux, le transporteur les administre.
 */
class CustomerIntegrationController extends Controller
{
    public function __construct(
        private readonly CustomerIntegrationScope $scope,
        private readonly CustomerIntegrationActivity $activity,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $failed = $this->scope->exportJobs()->where('status', 'failed')->whereNull('sent_at')->count();

        return ApiResponse::ok([
            'imports' => $this->imports()->resolve($request),
            'exports' => $this->exports()->resolve($request),
            'apiAccesses' => CustomerApiIntegrationResource::collection(
                $this->scope->apiConfigurations()->orderBy('name')->get(),
            )->resolve($request),
            'failedExportJobs' => $failed,
        ]);
    }

    public function importConfigurations(): JsonResponse
    {
        return ApiResponse::ok($this->imports());
    }

    public function exportConfigurations(): JsonResponse
    {
        return ApiResponse::ok($this->exports());
    }

    public function apiConfigurations(): JsonResponse
    {
        return ApiResponse::ok(CustomerApiIntegrationResource::collection(
            $this->scope->apiConfigurations()->orderBy('name')->get(),
        ));
    }

    private function imports(): AnonymousResourceCollection
    {
        $configurations = $this->scope->importConfigurations()->orderBy('name')->get();
        $runs = $this->activity->lastImportRuns($configurations->pluck('id')->all());

        return CustomerImportIntegrationResource::collection($configurations->map(
            static fn (CustomerImportConfiguration $configuration): CustomerImportIntegrationResource => new CustomerImportIntegrationResource($configuration, $runs[$configuration->id] ?? null),
        ));
    }

    private function exports(): AnonymousResourceCollection
    {
        $configurations = $this->scope->exportConfigurations()->orderBy('name')->get();
        $sent = $this->activity->lastExportsSent($configurations->pluck('id')->all());

        return CustomerExportIntegrationResource::collection($configurations->map(
            static fn (CustomerExportConfiguration $configuration): CustomerExportIntegrationResource => new CustomerExportIntegrationResource($configuration, $sent[$configuration->id] ?? null),
        ));
    }
}
