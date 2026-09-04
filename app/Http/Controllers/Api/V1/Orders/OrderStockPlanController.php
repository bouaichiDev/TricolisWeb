<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Stock\Services\OrderStockPlanner;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderStockPlanController extends Controller
{
    /**
     * Ce que la confirmation sortirait du stock.
     *
     * Permission requise : `orders.view`.
     *
     * Une ligne de commande ne dit pas où sa marchandise se trouve. Quand un
     * article dort dans plusieurs emplacements, il faut demander lequel vider —
     * et le demander **avant** la confirmation, plutôt que la refuser après.
     * Cette route existe pour cela : elle ne modifie rien.
     *
     * Chaque ligne porte un `state` :
     *
     * | `state` | Suite |
     * | --- | --- |
     * | `resolved` | rien à demander, `stockLocationId` est trouvé |
     * | `ambiguous` | faire choisir parmi `locations` |
     * | `insufficient` | aucun emplacement ne couvre la quantité |
     * | `untracked` | ligne hors catalogue ou article non entreposé |
     * | `consumed` | déjà sortie lors d'une confirmation précédente |
     */
    public function __invoke(Request $request, Order $order, OrderStockPlanner $planner): JsonResponse
    {
        $this->authorize('view', $order);

        return ApiResponse::ok($planner->plan($order));
    }
}
