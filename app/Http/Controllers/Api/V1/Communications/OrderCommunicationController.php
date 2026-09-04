<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Communications\ListCommunicationRequest;
use App\Http\Requests\Api\V1\Communications\StoreOrderCommunicationRequest;
use App\Http\Requests\Api\V1\Communications\UpdateOrderCommunicationRequest;
use App\Http\Resources\Api\V1\Communications\OrderCommunicationDetailResource;
use App\Http\Resources\Api\V1\Communications\OrderCommunicationListResource;
use App\Modules\Communications\Actions\CreateOrderCommunicationAction;
use App\Modules\Communications\Actions\UpdateDraftOrderCommunicationAction;
use App\Modules\Communications\DTOs\CreateOrderCommunicationData;
use App\Modules\Communications\DTOs\UpdateOrderCommunicationData;
use App\Modules\Communications\Exceptions\CommunicationNotEditable;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Communications\Queries\CommunicationListQuery;
use App\Modules\Orders\Models\Order;
use App\Modules\Templates\Exceptions\TemplateRenderingFailed;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Communications rattachées aux commandes.
 *
 * Les transitions — mise en file, annulation, relance — sont portées par
 * `OrderCommunicationStateController` : elles ont leurs propres permissions.
 */
class OrderCommunicationController extends Controller
{
    use BuildsAuditContext;
    use ResolvesCommunicationScope;

    /**
     * Lister les communications.
     *
     * Permission requise : `order_communications.view`. Tri par défaut : les
     * plus récentes d'abord.
     */
    public function index(ListCommunicationRequest $request, CommunicationListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [OrderCommunication::class, $organizationId]);

        return $this->respond($query->paginate('communication', $request, $organizationId));
    }

    /**
     * Créer une communication.
     *
     * Permission requise : `order_communications.create`. Avec un modèle, le
     * contenu est rendu et figé ; sans modèle, `body` est obligatoire. Le statut
     * initial est `scheduled` si `scheduledAt` est fourni, `draft` sinon.
     */
    public function store(StoreOrderCommunicationRequest $request, CreateOrderCommunicationAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [OrderCommunication::class, $organizationId]);

        try {
            $communication = $action->execute(
                CreateOrderCommunicationData::fromValidated($request->validated()),
                $this->auditContext($request, $organizationId),
            );
        } catch (TemplateRenderingFailed $exception) {
            return ApiResponse::validationError($exception->getMessage(), ['body' => [$exception->getMessage()]]);
        }

        return ApiResponse::created(new OrderCommunicationDetailResource($communication));
    }

    /**
     * Consulter une communication.
     */
    public function show(Request $request, OrderCommunication $orderCommunication): JsonResponse
    {
        $this->guardCommunication($orderCommunication);
        $this->authorize('view', $orderCommunication);

        return ApiResponse::ok(new OrderCommunicationDetailResource(
            $orderCommunication->load(['template', 'creator', 'attachments.document']),
        ));
    }

    /**
     * Modifier une communication en brouillon.
     *
     * Permission requise : `order_communications.update`. Refusé en 409 dès que
     * la communication a quitté le brouillon.
     */
    public function update(
        UpdateOrderCommunicationRequest $request,
        OrderCommunication $orderCommunication,
        UpdateDraftOrderCommunicationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCommunication($orderCommunication);
        $this->authorize('update', $orderCommunication);

        try {
            $updated = $action->execute(
                $orderCommunication,
                UpdateOrderCommunicationData::fromValidated($request->validated()),
                $this->auditContext($request, $organizationId),
            );
        } catch (CommunicationNotEditable $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::ok(new OrderCommunicationDetailResource($updated));
    }

    /**
     * Supprimer une communication en brouillon.
     *
     * Permission requise : `order_communications.delete`. Refusé en 409 au-delà
     * du brouillon : une communication engagée est une donnée historique.
     *
     * @response 204
     */
    public function destroy(
        Request $request,
        OrderCommunication $orderCommunication,
        UpdateDraftOrderCommunicationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCommunication($orderCommunication);
        $this->authorize('delete', $orderCommunication);

        try {
            $action->delete($orderCommunication, $this->auditContext($request, $organizationId));
        } catch (CommunicationNotEditable $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    /**
     * Communications d'une commande.
     */
    public function byOrder(ListCommunicationRequest $request, Order $order, CommunicationListQuery $query): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('viewAny', [OrderCommunication::class, $organizationId]);

        return $this->respond($query->paginate('communication', $request, $organizationId, ['order_id' => $order->id]));
    }

    /**
     * Créer une communication pour la commande de l'URL.
     */
    public function storeForOrder(
        StoreOrderCommunicationRequest $request,
        Order $order,
        CreateOrderCommunicationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardOrder($order);
        $this->authorize('create', [OrderCommunication::class, $organizationId]);

        try {
            $communication = $action->execute(
                CreateOrderCommunicationData::fromValidated($request->validated()),
                $this->auditContext($request, $organizationId),
            );
        } catch (TemplateRenderingFailed $exception) {
            return ApiResponse::validationError($exception->getMessage(), ['body' => [$exception->getMessage()]]);
        }

        return ApiResponse::created(new OrderCommunicationDetailResource($communication));
    }

    private function respond(mixed $paginator): JsonResponse
    {
        return ApiResponse::paginated(
            $paginator->through(fn (OrderCommunication $c) => new OrderCommunicationListResource($c)),
        );
    }
}
