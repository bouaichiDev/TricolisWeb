<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Stock\ApproveStockAdjustmentRequestRequest;
use App\Http\Requests\Api\V1\Stock\ListStockAdjustmentRequestRequest;
use App\Http\Requests\Api\V1\Stock\RejectStockAdjustmentRequestRequest;
use App\Http\Resources\Api\V1\Stock\StockAdjustmentRequestDetailResource;
use App\Http\Resources\Api\V1\Stock\StockAdjustmentRequestListResource;
use App\Modules\Customers\Models\Customer;
use App\Modules\Stock\Actions\ApproveStockAdjustmentRequestAction;
use App\Modules\Stock\Actions\RejectStockAdjustmentRequestAction;
use App\Modules\Stock\Exceptions\StockConflict;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Queries\StockAdjustmentRequestListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Demandes de correction de stock, côté transporteur.
 *
 * Lire : `stock_adjustment_requests.view`. Accepter ou refuser :
 * `stock_adjustment_requests.review`. Ni création, ni modification libre, ni
 * suppression : une demande naît au portail et ne se rejoue pas. Une demande
 * déjà tranchée, ou un mouvement que le stock refuse, répond 409.
 */
class StockAdjustmentRequestController extends Controller
{
    use BuildsAuditContext;

    public function index(ListStockAdjustmentRequestRequest $request, StockAdjustmentRequestListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [StockAdjustmentRequest::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId));
    }

    /** L'onglet Stock de la fiche client. */
    public function byCustomer(ListStockAdjustmentRequestRequest $request, Customer $customer, StockAdjustmentRequestListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($customer->organization_id === $organizationId, 404, 'Client introuvable.');
        $this->authorize('viewAny', [StockAdjustmentRequest::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['customer_id' => $customer->id]));
    }

    public function show(StockAdjustmentRequest $stockAdjustmentRequest): JsonResponse
    {
        $this->guard($stockAdjustmentRequest);
        $this->authorize('view', $stockAdjustmentRequest);

        return $this->detail($stockAdjustmentRequest);
    }

    /** 409 si la demande est déjà tranchée, ou si le stock refuse la sortie. */
    public function approve(ApproveStockAdjustmentRequestRequest $request, StockAdjustmentRequest $stockAdjustmentRequest, ApproveStockAdjustmentRequestAction $action): JsonResponse
    {
        $organizationId = $this->guard($stockAdjustmentRequest);
        $this->authorize('review', $stockAdjustmentRequest);

        try {
            $approved = $action->execute(
                $stockAdjustmentRequest,
                $request->validated('stockLocationId'),
                $request->validated('reviewComment'),
                $this->auditContext($request, $organizationId),
                now()->toDateTimeString(),
            );
        } catch (StockConflict $conflict) {
            return ApiResponse::error($conflict->getMessage(), 409);
        }

        return $this->detail($approved);
    }

    /** 409 si la demande est déjà tranchée. Aucune écriture de stock. */
    public function reject(RejectStockAdjustmentRequestRequest $request, StockAdjustmentRequest $stockAdjustmentRequest, RejectStockAdjustmentRequestAction $action): JsonResponse
    {
        $organizationId = $this->guard($stockAdjustmentRequest);
        $this->authorize('review', $stockAdjustmentRequest);

        try {
            $rejected = $action->execute(
                $stockAdjustmentRequest,
                (string) $request->validated('reviewComment'),
                $this->auditContext($request, $organizationId),
                now()->toDateTimeString(),
            );
        } catch (StockConflict $conflict) {
            return ApiResponse::error($conflict->getMessage(), 409);
        }

        return $this->detail($rejected);
    }

    /** Une demande d'une autre organisation n'existe pas : 404, jamais 403. */
    private function guard(StockAdjustmentRequest $adjustment): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($adjustment->organization_id === $organizationId, 404, 'Demande introuvable.');

        return $organizationId;
    }

    private function detail(StockAdjustmentRequest $adjustment): JsonResponse
    {
        return ApiResponse::ok(new StockAdjustmentRequestDetailResource(
            $adjustment->load([...StockAdjustmentRequestListQuery::RELATIONS, 'stockMovement']),
        ));
    }

    private function respond(LengthAwarePaginator $paginator): JsonResponse
    {
        return ApiResponse::paginated(
            $paginator->through(static fn (StockAdjustmentRequest $adjustment) => new StockAdjustmentRequestListResource($adjustment)),
        );
    }
}
