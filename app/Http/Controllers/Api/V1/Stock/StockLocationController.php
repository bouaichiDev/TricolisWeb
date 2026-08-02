<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Stock\ListStockLocationRequest;
use App\Http\Requests\Api\V1\Stock\StoreStockLocationRequest;
use App\Http\Requests\Api\V1\Stock\UpdateStockLocationRequest;
use App\Http\Resources\Api\V1\Stock\StockLocationDetailResource;
use App\Http\Resources\Api\V1\Stock\StockLocationListResource;
use App\Http\Resources\Api\V1\Stock\StockLocationTreeResource;
use App\Modules\Stock\Actions\CreateStockLocationAction;
use App\Modules\Stock\Actions\DeleteStockLocationAction;
use App\Modules\Stock\Actions\UpdateStockLocationAction;
use App\Modules\Stock\DTOs\CreateStockLocationData;
use App\Modules\Stock\DTOs\UpdateStockLocationData;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockLocation;
use App\Modules\Stock\Queries\StockLocationListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Emplacements de stock.
 */
class StockLocationController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les emplacements.
     *
     * Permission requise : `stock_locations.view`. Le périmètre passe par
     * `depot.agency.organization_id`.
     */
    public function index(ListStockLocationRequest $request, StockLocationListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [StockLocation::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated($paginator->through(fn (StockLocation $l) => new StockLocationListResource($l)));
    }

    /**
     * Arbre des emplacements.
     *
     * Permission requise : `stock_locations.view`. L'arbre est dérivé de
     * `stock_locations` par un seul `SELECT` : aucune table supplémentaire.
     * Le paramètre `depotId` restreint à un dépôt.
     */
    public function tree(Request $request, StockLocationListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [StockLocation::class, $organizationId]);

        $roots = $query->tree($organizationId, $request->query('depotId'));

        return ApiResponse::ok(StockLocationTreeResource::collection($roots));
    }

    /**
     * Créer un emplacement.
     *
     * Permission requise : `stock_locations.create`. Le parent, s'il est
     * fourni, doit relever du même dépôt.
     */
    public function store(StoreStockLocationRequest $request, CreateStockLocationAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [StockLocation::class, $organizationId]);

        $location = $action->execute(
            CreateStockLocationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new StockLocationDetailResource($location));
    }

    /**
     * Consulter un emplacement, avec son parent et ses enfants.
     */
    public function show(Request $request, StockLocation $stockLocation): JsonResponse
    {
        $this->guardLocation($stockLocation);
        $this->authorize('view', $stockLocation);

        return ApiResponse::ok(new StockLocationDetailResource(
            $stockLocation->load(['parent', 'children']),
        ));
    }

    /**
     * Modifier un emplacement, réorganisation comprise.
     *
     * Permission requise : `stock_locations.update`. Changer de parent passe
     * par le contrôle de cycle ; le dépôt n'est pas modifiable.
     */
    public function update(UpdateStockLocationRequest $request, StockLocation $stockLocation, UpdateStockLocationAction $action): JsonResponse
    {
        $organizationId = $this->guardLocation($stockLocation);
        $this->authorize('update', $stockLocation);

        $updated = $action->execute(
            $stockLocation,
            UpdateStockLocationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new StockLocationDetailResource($updated));
    }

    /**
     * Supprimer un emplacement vide.
     *
     * Permission requise : `stock_locations.delete`. Refusé en 409 s'il porte
     * des enfants, du stock ou une réservation active.
     *
     * @response 204
     */
    public function destroy(Request $request, StockLocation $stockLocation, DeleteStockLocationAction $action): JsonResponse
    {
        $organizationId = $this->guardLocation($stockLocation);
        $this->authorize('delete', $stockLocation);

        try {
            $action->execute($stockLocation, $this->auditContext($request, $organizationId));
        } catch (StockConflict $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    private function guardLocation(StockLocation $location): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless(
            $location->depot?->agency?->organization_id === $organizationId,
            404,
            'Emplacement introuvable.',
        );

        return $organizationId;
    }
}
