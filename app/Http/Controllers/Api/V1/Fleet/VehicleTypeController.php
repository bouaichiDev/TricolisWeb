<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Fleet\StoreVehicleTypeRequest;
use App\Http\Requests\Api\V1\Fleet\UpdateVehicleTypeRequest;
use App\Http\Resources\Api\V1\Fleet\VehicleTypeDetailResource;
use App\Http\Resources\Api\V1\Fleet\VehicleTypeListResource;
use App\Modules\Fleet\Actions\CreateVehicleTypeAction;
use App\Modules\Fleet\Actions\DeleteVehicleTypeAction;
use App\Modules\Fleet\Actions\UpdateVehicleTypeAction;
use App\Modules\Fleet\DTOs\CreateVehicleTypeData;
use App\Modules\Fleet\DTOs\UpdateVehicleTypeData;
use App\Modules\Fleet\Exceptions\VehicleTypeStillInUse;
use App\Modules\Fleet\Models\VehicleType;
use App\Modules\Fleet\Queries\VehicleTypeListQuery;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel des types de véhicule de l'organisation active.
 */
class VehicleTypeController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les types de véhicule.
     *
     * Permission requise : `vehicle_types.view`. Recherche sur `code` et
     * `name`, filtre `status`, tri sur `code`, `name`, `status`.
     */
    public function index(ListRequest $request, VehicleTypeListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [VehicleType::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated($paginator->through(fn (VehicleType $t) => new VehicleTypeListResource($t)));
    }

    /**
     * Créer un type de véhicule.
     *
     * Permission requise : `vehicle_types.create`. Le code est unique dans
     * l'organisation active.
     */
    public function store(StoreVehicleTypeRequest $request, CreateVehicleTypeAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [VehicleType::class, $organizationId]);

        $type = $action->execute(
            CreateVehicleTypeData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new VehicleTypeDetailResource($type));
    }

    /**
     * Consulter un type de véhicule.
     *
     * Permission requise : `vehicle_types.view`.
     */
    public function show(Request $request, VehicleType $vehicleType): JsonResponse
    {
        $this->guardScope($vehicleType);
        $this->authorize('view', $vehicleType);

        return ApiResponse::ok(new VehicleTypeDetailResource(
            $vehicleType->load('organization')->loadCount('vehicles'),
        ));
    }

    /**
     * Modifier un type de véhicule.
     *
     * Permission requise : `vehicle_types.update`. Les véhicules rattachés ne
     * sont pas modifiés.
     */
    public function update(UpdateVehicleTypeRequest $request, VehicleType $vehicleType, UpdateVehicleTypeAction $action): JsonResponse
    {
        $organizationId = $this->guardScope($vehicleType);
        $this->authorize('update', $vehicleType);

        $updated = $action->execute(
            $vehicleType,
            UpdateVehicleTypeData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new VehicleTypeDetailResource($updated));
    }

    /**
     * Supprimer un type de véhicule.
     *
     * Permission requise : `vehicle_types.delete`. Refusé en 409 s'il est
     * utilisé par un véhicule ; les véhicules ne sont jamais supprimés en
     * cascade.
     *
     * @response 204
     */
    public function destroy(Request $request, VehicleType $vehicleType, DeleteVehicleTypeAction $action): JsonResponse
    {
        $organizationId = $this->guardScope($vehicleType);
        $this->authorize('delete', $vehicleType);

        try {
            $action->execute($vehicleType, $this->auditContext($request, $organizationId));
        } catch (VehicleTypeStillInUse $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    private function guardScope(VehicleType $vehicleType): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($vehicleType->organization_id === $organizationId, 404, 'Type de véhicule introuvable.');

        return $organizationId;
    }
}
