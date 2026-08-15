<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTourScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tracking\ListTrackingEventRequest;
use App\Http\Requests\Api\V1\Tracking\StoreTrackingEventRequest;
use App\Http\Resources\Api\V1\Tracking\TrackingEventDetailResource;
use App\Http\Resources\Api\V1\Tracking\TrackingEventListResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tracking\Actions\CreateTrackingEventAction;
use App\Modules\Tracking\DTOs\CreateTrackingEventData;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Queries\TrackingEventListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Événements de suivi.
 *
 * Ni `update`, ni `destroy` : un événement est une donnée historique. Une
 * nouvelle occurrence produit un nouvel événement. Les routes n'existent pas,
 * plutôt que d'exister en renvoyant 403.
 */
class TrackingEventController extends Controller
{
    use BuildsAuditContext;
    use ResolvesTourScope;

    /**
     * Lister les événements de suivi de l'organisation active.
     *
     * Permission requise : `tracking_events.view`. Recherche sur `description`,
     * `event_type` et `status` ; ordre par défaut `occurred_at` décroissant.
     */
    public function index(ListTrackingEventRequest $request, TrackingEventListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [TrackingEvent::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId));
    }

    /**
     * Créer un événement de suivi.
     *
     * Permission requise : `tracking_events.create`. L'organisation est prise
     * sur la commande ; l'auteur est l'utilisateur authentifié.
     */
    public function store(StoreTrackingEventRequest $request, CreateTrackingEventAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [TrackingEvent::class, $organizationId]);

        $event = $action->execute(
            CreateTrackingEventData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new TrackingEventDetailResource($event));
    }

    /**
     * Consulter un événement de suivi.
     *
     * Permission requise : `tracking_events.view`.
     */
    public function show(Request $request, TrackingEvent $trackingEvent): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($trackingEvent->organization_id === $organizationId, 404, 'Événement introuvable.');
        $this->authorize('view', $trackingEvent);

        return ApiResponse::ok(new TrackingEventDetailResource($trackingEvent->load('creator')));
    }

    /**
     * Événements d'une commande.
     */
    public function byOrder(ListTrackingEventRequest $request, Order $order, TrackingEventListQuery $query): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('viewAny', [TrackingEvent::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['order_id' => $order->id]));
    }

    /**
     * Événements d'un service de commande.
     */
    public function byOrderService(ListTrackingEventRequest $request, Order $order, OrderService $orderService, TrackingEventListQuery $query): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        abort_unless($orderService->order_id === $order->id, 404, 'Service introuvable.');
        $this->authorize('viewAny', [TrackingEvent::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['order_service_id' => $orderService->id]));
    }

    /**
     * Événements d'une tournée.
     */
    public function byTour(ListTrackingEventRequest $request, Tour $tour, TrackingEventListQuery $query): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->authorize('viewAny', [TrackingEvent::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['tour_id' => $tour->id]));
    }

    /**
     * Événements d'un arrêt de tournée.
     */
    public function byTourStop(ListTrackingEventRequest $request, Tour $tour, TourStop $tourStop, TrackingEventListQuery $query): JsonResponse
    {
        $organizationId = $this->guardStop($tour, $tourStop);
        $this->authorize('viewAny', [TrackingEvent::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['tour_stop_id' => $tourStop->id]));
    }

    private function respond(mixed $paginator): JsonResponse
    {
        return ApiResponse::paginated($paginator->through(fn (TrackingEvent $e) => new TrackingEventListResource($e)));
    }

    private function guardOrder(Order $order): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($order->organization_id === $organizationId, 404, 'Commande introuvable.');

        return $organizationId;
    }
}
