<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Drivers;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Drivers\ListDriverRequest;
use App\Http\Requests\Api\V1\Drivers\StoreDriverRequest;
use App\Http\Requests\Api\V1\Drivers\UpdateDriverRequest;
use App\Http\Resources\Api\V1\Drivers\DriverDetailResource;
use App\Http\Resources\Api\V1\Drivers\DriverListResource;
use App\Modules\Drivers\Actions\CreateDriverAction;
use App\Modules\Drivers\Actions\DeleteDriverAction;
use App\Modules\Drivers\Actions\UpdateDriverAction;
use App\Modules\Drivers\DTOs\CreateDriverData;
use App\Modules\Drivers\DTOs\UpdateDriverData;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Drivers\Queries\DriverListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chauffeurs des fournisseurs de l'organisation active.
 *
 * Le chauffeur n'ayant pas d'organisation propre, son périmètre est celui de
 * son fournisseur : c'est ce que vérifie `guardScope`.
 */
class DriverController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les chauffeurs.
     *
     * Permission requise : `drivers.view`. Recherche sur `code`, `first_name`,
     * `last_name`, `phone`, `email` ; filtres `providerId`, `userId`, `status`,
     * `legacyId` ; tri sur `code`, `first_name`, `last_name`, `status`,
     * `legacy_id`.
     */
    public function index(ListDriverRequest $request, DriverListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Driver::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated($paginator->through(fn (Driver $d) => new DriverListResource($d)));
    }

    /**
     * Créer un chauffeur.
     *
     * Permission requise : `drivers.create`. Le fournisseur doit appartenir à
     * l'organisation active, et le compte lié en être membre. Le code est
     * unique chez le fournisseur.
     */
    public function store(StoreDriverRequest $request, CreateDriverAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Driver::class, $organizationId]);

        $driver = $action->execute(
            CreateDriverData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new DriverDetailResource($driver->load('provider')));
    }

    /**
     * Consulter un chauffeur.
     *
     * Permission requise : `drivers.view`. Un chauffeur hors périmètre renvoie
     * 404. Le compte lié n'est exposé que par son nom et son email.
     */
    public function show(Request $request, Driver $driver): JsonResponse
    {
        $this->guardScope($driver);
        $this->authorize('view', $driver);

        return ApiResponse::ok(new DriverDetailResource($driver->load(['provider', 'user'])));
    }

    /**
     * Modifier un chauffeur.
     *
     * Permission requise : `drivers.update`. Réaffecter le chauffeur reste
     * possible, mais seulement vers un fournisseur de la même organisation.
     */
    public function update(UpdateDriverRequest $request, Driver $driver, UpdateDriverAction $action): JsonResponse
    {
        $organizationId = $this->guardScope($driver);
        $this->authorize('update', $driver);

        $updated = $action->execute(
            $driver,
            UpdateDriverData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new DriverDetailResource($updated->load('provider')));
    }

    /**
     * Supprimer un chauffeur.
     *
     * Permission requise : `drivers.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Driver $driver, DeleteDriverAction $action): JsonResponse
    {
        $organizationId = $this->guardScope($driver);
        $this->authorize('delete', $driver);

        $action->execute($driver, $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }

    private function guardScope(Driver $driver): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($driver->provider?->organization_id === $organizationId, 404, 'Chauffeur introuvable.');

        return $organizationId;
    }
}
