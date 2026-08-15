<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ProviderSettlements;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProviderSettlements\ListProviderSettlementRequest;
use App\Http\Requests\Api\V1\ProviderSettlements\StoreProviderSettlementRequest;
use App\Http\Requests\Api\V1\ProviderSettlements\UpdateProviderSettlementRequest;
use App\Http\Resources\Api\V1\ProviderSettlements\ProviderSettlementDetailResource;
use App\Http\Resources\Api\V1\ProviderSettlements\ProviderSettlementListResource;
use App\Modules\Providers\Models\Provider;
use App\Modules\ProviderSettlements\Actions\CreateProviderSettlementAction;
use App\Modules\ProviderSettlements\Actions\DeleteProviderSettlementAction;
use App\Modules\ProviderSettlements\Actions\UpdateProviderSettlementAction;
use App\Modules\ProviderSettlements\DTOs\CreateProviderSettlementData;
use App\Modules\ProviderSettlements\DTOs\UpdateProviderSettlementData;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Queries\ProviderSettlementListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Décomptes fournisseurs.
 */
class ProviderSettlementController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les décomptes.
     *
     * Permission requise : `provider_settlements.view`.
     */
    public function index(ListProviderSettlementRequest $request, ProviderSettlementListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [ProviderSettlement::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId));
    }

    /**
     * Créer un décompte avec ses lignes.
     *
     * Permission requise : `provider_settlements.create`. Au moins une ligne
     * est exigée ; `subtotal` et `total` sont calculés, `taxTotal` est saisi.
     */
    public function store(StoreProviderSettlementRequest $request, CreateProviderSettlementAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [ProviderSettlement::class, $organizationId]);

        $settlement = $action->execute(
            CreateProviderSettlementData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ProviderSettlementDetailResource($settlement->load('lines')));
    }

    /**
     * Consulter un décompte, lignes comprises.
     */
    public function show(Request $request, ProviderSettlement $providerSettlement): JsonResponse
    {
        $this->guardSettlement($providerSettlement);
        $this->authorize('view', $providerSettlement);

        return ApiResponse::ok(new ProviderSettlementDetailResource(
            $providerSettlement->load(['provider', 'lines'])->loadCount('lines'),
        ));
    }

    /**
     * Modifier l'en-tête d'un décompte.
     *
     * Permission requise : `provider_settlements.update`. Modifier `taxTotal`
     * recalcule `total`.
     */
    public function update(UpdateProviderSettlementRequest $request, ProviderSettlement $providerSettlement, UpdateProviderSettlementAction $action): JsonResponse
    {
        $organizationId = $this->guardSettlement($providerSettlement);
        $this->authorize('update', $providerSettlement);

        $updated = $action->execute(
            $providerSettlement,
            UpdateProviderSettlementData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new ProviderSettlementDetailResource($updated));
    }

    /**
     * Supprimer un décompte et ses lignes.
     *
     * @response 204
     */
    public function destroy(Request $request, ProviderSettlement $providerSettlement, DeleteProviderSettlementAction $action): JsonResponse
    {
        $organizationId = $this->guardSettlement($providerSettlement);
        $this->authorize('delete', $providerSettlement);

        $action->execute($providerSettlement, $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }

    /**
     * Décomptes d'un fournisseur.
     */
    public function byProvider(ListProviderSettlementRequest $request, Provider $provider, ProviderSettlementListQuery $query): JsonResponse
    {
        $organizationId = $this->guardProvider($provider);
        $this->authorize('viewAny', [ProviderSettlement::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['provider_id' => $provider->id]));
    }

    /**
     * Créer un décompte pour le fournisseur de l'URL.
     */
    public function storeForProvider(StoreProviderSettlementRequest $request, Provider $provider, CreateProviderSettlementAction $action): JsonResponse
    {
        $organizationId = $this->guardProvider($provider);
        $this->authorize('create', [ProviderSettlement::class, $organizationId]);

        $settlement = $action->execute(
            CreateProviderSettlementData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ProviderSettlementDetailResource($settlement->load('lines')));
    }

    private function respond(mixed $paginator): JsonResponse
    {
        return ApiResponse::paginated(
            $paginator->through(fn (ProviderSettlement $s) => new ProviderSettlementListResource($s)),
        );
    }

    private function guardSettlement(ProviderSettlement $settlement): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($settlement->organization_id === $organizationId, 404, 'Décompte introuvable.');

        return $organizationId;
    }

    private function guardProvider(Provider $provider): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($provider->organization_id === $organizationId, 404, 'Fournisseur introuvable.');

        return $organizationId;
    }
}
