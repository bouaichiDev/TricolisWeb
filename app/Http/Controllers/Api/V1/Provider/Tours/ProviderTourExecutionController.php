<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Tours;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\Tours\ExecuteTourRequest;
use App\Http\Resources\Api\V1\Provider\ProviderTourResource;
use App\Http\Resources\Api\V1\Provider\ProviderTourStopResource;
use App\Modules\ProviderPortal\Actions\ExecuteProviderStop;
use App\Modules\ProviderPortal\Actions\ExecuteProviderTour;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\AuditContext;
use Illuminate\Http\JsonResponse;

/**
 * Exécution des tournées : démarrer, terminer, et faire avancer les arrêts.
 * Permissions : `provider.tours.execute` (tournée), `provider.tour-stops.update-status`
 * (arrêts).
 */
class ProviderTourExecutionController extends Controller
{
    public function __construct(
        private readonly CurrentProviderContext $context,
        private readonly ExecuteProviderTour $tours,
        private readonly ExecuteProviderStop $stops,
    ) {}

    public function start(ExecuteTourRequest $request, string $tour): JsonResponse
    {
        return $this->tour($this->tours->start($tour, $this->portalAudit($request)));
    }

    public function complete(ExecuteTourRequest $request, string $tour): JsonResponse
    {
        return $this->tour($this->tours->complete($tour, $this->portalAudit($request)));
    }

    public function arrive(ExecuteTourRequest $request, string $tour, string $stop): JsonResponse
    {
        return $this->stop($this->stops->arrive($tour, $stop, $this->portalAudit($request)));
    }

    public function completeStop(ExecuteTourRequest $request, string $tour, string $stop): JsonResponse
    {
        return $this->stop($this->stops->complete($tour, $stop, $this->portalAudit($request)));
    }

    public function skip(ExecuteTourRequest $request, string $tour, string $stop): JsonResponse
    {
        return $this->stop($this->stops->skip($tour, $stop, (string) $request->validated('reason'), $this->portalAudit($request)));
    }

    private function tour(Tour $tour): JsonResponse
    {
        return ApiResponse::ok(new ProviderTourResource($tour->load(['agency:id,name', 'depot:id,name', 'driver:id,code,name', 'vehicle:id,code,registration_number'])));
    }

    private function stop(TourStop $stop): JsonResponse
    {
        return ApiResponse::ok(new ProviderTourStopResource($stop->load(['tour', 'address', 'services.orderService.service:id,name', 'services.orderService.contacts'])));
    }

    private function portalAudit(ExecuteTourRequest $request): AuditContext
    {
        return new AuditContext($this->context->organizationId(), $this->context->user(), $request->ip());
    }
}
