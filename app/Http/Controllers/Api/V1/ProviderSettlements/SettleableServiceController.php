<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ProviderSettlements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProviderSettlements\ListSettleableServiceRequest;
use App\Http\Resources\Api\V1\ProviderSettlements\SettleableServiceResource;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Providers\Models\Provider;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Modules\ProviderSettlements\Queries\SettleableServiceQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Les prestations encore à régler à un fournisseur.
 *
 * Le fournisseur retenu est celui de l'affectation **active** du service : le
 * §17 refuse qu'on paie une tentative échouée simplement parce qu'elle figure
 * dans l'historique.
 *
 * Permission requise : `provider_settlements.view`.
 */
class SettleableServiceController extends Controller
{
    public function index(ListSettleableServiceRequest $request, SettleableServiceQuery $query, Provider $provider): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($provider->organization_id === $organizationId, 404, 'Fournisseur introuvable.');
        $this->authorize('viewAny', [ProviderSettlement::class, $organizationId]);

        $paginator = $query->paginate($request, $provider->id, $organizationId);

        return ApiResponse::paginated($paginator->through(
            fn (OrderService $service) => new SettleableServiceResource($service),
        ));
    }
}
