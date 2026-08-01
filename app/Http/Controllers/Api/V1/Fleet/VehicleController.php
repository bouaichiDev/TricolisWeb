<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Fleet\ListVehicleRequest;
use App\Http\Requests\Api\V1\Fleet\StoreVehicleRequest;
use App\Http\Requests\Api\V1\Fleet\UpdateVehicleRequest;
use App\Http\Resources\Api\V1\Fleet\VehicleDetailResource;
use App\Http\Resources\Api\V1\Fleet\VehicleListResource;
use App\Modules\Fleet\Actions\CreateVehicleAction;
use App\Modules\Fleet\Actions\DeleteVehicleAction;
use App\Modules\Fleet\Actions\UpdateVehicleAction;
use App\Modules\Fleet\DTOs\CreateVehicleData;
use App\Modules\Fleet\DTOs\UpdateVehicleData;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Queries\VehicleListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Véhicules des fournisseurs de l'organisation active.
 */
class VehicleController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les véhicules.
     *
     * Permission requise : `vehicles.view`. Recherche sur `code` et
     * `registration_number` ; filtres `providerId`, `vehicleTypeId`, `status`,
     * `payloadCapacityMin`, `volumeCapacityMin`, `palletCapacityMin` ; tri sur
     * les capacités, le code, l'immatriculation et le statut.
     */
    public function index(ListVehicleRequest $request, VehicleListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Vehicle::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated($paginator->through(fn (Vehicle $v) => new VehicleListResource($v)));
    }

    /**
     * Créer un véhicule.
     *
     * Permission requise : `vehicles.create`. Le fournisseur et le type doivent
     * appartenir à l'organisation active — et à la même. Le code est unique
     * chez le fournisseur, l'immatriculation sur toute la plateforme.
     */
    public function store(StoreVehicleRequest $request, CreateVehicleAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Vehicle::class, $organizationId]);

        $vehicle = $action->execute(
            CreateVehicleData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new VehicleDetailResource($vehicle->load(['provider', 'vehicleType'])));
    }

    /**
     * Consulter un véhicule.
     *
     * Permission requise : `vehicles.view`. Un véhicule hors périmètre renvoie 404.
     */
    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->guardScope($vehicle);
        $this->authorize('view', $vehicle);

        return ApiResponse::ok(new VehicleDetailResource($vehicle->load(['provider', 'vehicleType'])));
    }

    /**
     * Modifier un véhicule.
     *
     * Permission requise : `vehicles.update`. Changer de fournisseur ou de type
     * repasse par les mêmes contrôles qu'à la création.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle, UpdateVehicleAction $action): JsonResponse
    {
        $organizationId = $this->guardScope($vehicle);
        $this->authorize('update', $vehicle);

        $updated = $action->execute(
            $vehicle,
            UpdateVehicleData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new VehicleDetailResource($updated->load(['provider', 'vehicleType'])));
    }

    /**
     * Supprimer un véhicule.
     *
     * Permission requise : `vehicles.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Vehicle $vehicle, DeleteVehicleAction $action): JsonResponse
    {
        $organizationId = $this->guardScope($vehicle);
        $this->authorize('delete', $vehicle);

        $action->execute($vehicle, $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }

    private function guardScope(Vehicle $vehicle): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($vehicle->provider?->organization_id === $organizationId, 404, 'Véhicule introuvable.');

        return $organizationId;
    }
}
