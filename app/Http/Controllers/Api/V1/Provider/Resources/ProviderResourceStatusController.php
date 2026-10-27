<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Resources;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Provider\ProviderDriverResource;
use App\Http\Resources\Api\V1\Provider\ProviderVehicleResource;
use App\Modules\ProviderPortal\Actions\ChangeProviderResourceStatus;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderResourceScope;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\AuditContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Désactiver ou réactiver un chauffeur ou un véhicule du fournisseur courant.
 * Jamais de suppression : une ressource qui a roulé garde son historique.
 */
class ProviderResourceStatusController extends Controller
{
    public function __construct(
        private readonly CurrentProviderContext $context,
        private readonly ProviderResourceScope $scope,
        private readonly ChangeProviderResourceStatus $change,
    ) {}

    public function deactivateDriver(Request $request, string $driver): JsonResponse
    {
        return $this->driver($request, $driver, ChangeProviderResourceStatus::INACTIVE);
    }

    public function activateDriver(Request $request, string $driver): JsonResponse
    {
        return $this->driver($request, $driver, ChangeProviderResourceStatus::ACTIVE);
    }

    public function deactivateVehicle(Request $request, string $vehicle): JsonResponse
    {
        return $this->vehicle($request, $vehicle, ChangeProviderResourceStatus::INACTIVE);
    }

    public function activateVehicle(Request $request, string $vehicle): JsonResponse
    {
        return $this->vehicle($request, $vehicle, ChangeProviderResourceStatus::ACTIVE);
    }

    private function driver(Request $request, string $id, string $status): JsonResponse
    {
        $driver = DB::transaction(fn () => $this->change->execute($this->scope->driver($id, lock: true), $status, $this->portalAudit($request)));

        return ApiResponse::ok(new ProviderDriverResource($driver->load('user')));
    }

    private function vehicle(Request $request, string $id, string $status): JsonResponse
    {
        $vehicle = DB::transaction(fn () => $this->change->execute($this->scope->vehicle($id, lock: true), $status, $this->portalAudit($request)));

        return ApiResponse::ok(new ProviderVehicleResource($vehicle->load('vehicleType')));
    }

    private function portalAudit(Request $request): AuditContext
    {
        return new AuditContext($this->context->organizationId(), $this->context->user(), $request->ip());
    }
}
