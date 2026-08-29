<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pricing\ListPrebillingRequest;
use App\Http\Resources\Api\V1\Pricing\PrebillingServiceResource;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Pricing\Actions\CalculateOrderServicePrice;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Queries\PrebillingQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Ce qui reste à facturer, avec le tarif que le barème donnerait.
 *
 * **Le calcul n'est pas enregistré** : le §169AH interdit de retenir un aperçu
 * comme prix définitif. On regarde ici, on décide ailleurs.
 *
 * Le calcul est fait page par page, jamais sur toute la base : une organisation
 * qui a mille prestations en attente n'attendrait pas mille résolutions pour
 * voir vingt-cinq lignes.
 */
class PrebillingController extends Controller
{
    public function __construct(private readonly CalculateOrderServicePrice $calculate) {}

    /**
     * Lister les prestations à facturer et leur tarif calculé.
     *
     * Permission requise : `pricing_calculations.view` — c'est une lecture de
     * tarifs, pas une écriture de facture.
     */
    public function index(ListPrebillingRequest $request, PrebillingQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [PriceList::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated($paginator->through(
            fn (OrderService $service) => new PrebillingServiceResource(
                $service,
                $this->calculate->execute($service, $organizationId, record: false),
            ),
        ));
    }
}
