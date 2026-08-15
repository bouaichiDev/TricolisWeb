<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ProviderSettlements;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProviderSettlements\StoreProviderSettlementLineRequest;
use App\Http\Requests\Api\V1\ProviderSettlements\UpdateProviderSettlementLineRequest;
use App\Http\Resources\Api\V1\ProviderSettlements\ProviderSettlementLineResource;
use App\Modules\ProviderSettlements\Actions\AddProviderSettlementLineAction;
use App\Modules\ProviderSettlements\Actions\RemoveProviderSettlementLineAction;
use App\Modules\ProviderSettlements\Actions\UpdateProviderSettlementLineAction;
use App\Modules\ProviderSettlements\DTOs\CreateProviderSettlementLineData;
use App\Modules\ProviderSettlements\DTOs\UpdateProviderSettlementLineData;
use App\Modules\ProviderSettlements\Exceptions\SettlementLineRequired;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lignes d'un décompte fournisseur.
 */
class ProviderSettlementLineController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les lignes d'un décompte.
     *
     * Permission requise : `provider_settlement_lines.view`.
     */
    public function index(ListRequest $request, ProviderSettlement $providerSettlement): JsonResponse
    {
        $organizationId = $this->guardSettlement($providerSettlement);
        $this->authorize('viewAny', [ProviderSettlementLine::class, $organizationId]);

        $paginator = $providerSettlement->lines()->paginate($request->getPerPage());

        return ApiResponse::paginated(
            $paginator->through(fn (ProviderSettlementLine $l) => new ProviderSettlementLineResource($l)),
        );
    }

    /**
     * Ajouter une ligne.
     *
     * Permission requise : `provider_settlement_lines.create`. Le service doit
     * être compatible avec le fournisseur du décompte.
     */
    public function store(StoreProviderSettlementLineRequest $request, ProviderSettlement $providerSettlement, AddProviderSettlementLineAction $action): JsonResponse
    {
        $organizationId = $this->guardSettlement($providerSettlement);
        $this->authorize('create', [ProviderSettlementLine::class, $organizationId]);

        $line = $action->execute(
            $providerSettlement,
            CreateProviderSettlementLineData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ProviderSettlementLineResource($line));
    }

    /**
     * Consulter une ligne.
     */
    public function show(Request $request, ProviderSettlement $providerSettlement, ProviderSettlementLine $line): JsonResponse
    {
        $this->guardLine($providerSettlement, $line);
        $this->authorize('view', $line);

        return ApiResponse::ok(new ProviderSettlementLineResource($line));
    }

    /**
     * Modifier une ligne.
     *
     * Permission requise : `provider_settlement_lines.update`.
     */
    public function update(UpdateProviderSettlementLineRequest $request, ProviderSettlement $providerSettlement, ProviderSettlementLine $line, UpdateProviderSettlementLineAction $action): JsonResponse
    {
        $organizationId = $this->guardLine($providerSettlement, $line);
        $this->authorize('update', $line);

        $updated = $action->execute(
            $line,
            UpdateProviderSettlementLineData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new ProviderSettlementLineResource($updated));
    }

    /**
     * Retirer une ligne.
     *
     * Permission requise : `provider_settlement_lines.delete`. Refusé en 409 si
     * c'est la dernière.
     *
     * @response 204
     */
    public function destroy(Request $request, ProviderSettlement $providerSettlement, ProviderSettlementLine $line, RemoveProviderSettlementLineAction $action): JsonResponse
    {
        $organizationId = $this->guardLine($providerSettlement, $line);
        $this->authorize('delete', $line);

        try {
            $action->execute($line, $this->auditContext($request, $organizationId));
        } catch (SettlementLineRequired $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    private function guardSettlement(ProviderSettlement $settlement): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($settlement->organization_id === $organizationId, 404, 'Décompte introuvable.');

        return $organizationId;
    }

    private function guardLine(ProviderSettlement $settlement, ProviderSettlementLine $line): string
    {
        $organizationId = $this->guardSettlement($settlement);
        abort_unless($line->settlement_id === $settlement->id, 404, 'Ligne introuvable.');

        return $organizationId;
    }
}
