<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Audit\AuditLogResource;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Historique d'une commande.
 *
 * Il n'existe pas de table d'historique : le diagramme n'en prévoit aucune, et
 * le journal d'audit enregistre déjà chaque transition avec son ancien et son
 * nouveau statut. L'historique en est dérivé, ce qui évite de dupliquer la même
 * information dans deux tables susceptibles de diverger.
 */
class OrderHistoryController extends Controller
{
    use ResolvesOrderScope;

    /**
     * Consulter l'historique d'une commande.
     *
     * Permission requise : `orders.view`. Les entrées sont triées de la plus
     * récente à la plus ancienne et suivent la rétention du journal d'audit.
     */
    public function index(ListRequest $request, Order $order): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('view', $order);

        $logs = AuditLog::where('organization_id', $organizationId)
            ->where('entity_type', MorphMap::ORDER)
            ->where('entity_id', $order->id)
            ->latest('created_at')
            ->paginate($request->getPerPage());

        return ApiResponse::paginated($logs->through(fn (AuditLog $log) => new AuditLogResource($log)));
    }
}
