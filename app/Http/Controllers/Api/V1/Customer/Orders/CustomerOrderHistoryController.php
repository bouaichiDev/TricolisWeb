<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Orders;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerOrder;
use App\Http\Controllers\Controller;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerOrderHistory;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Historique fonctionnel d'une commande. Permission : `customer.orders.history.view`.
 */
class CustomerOrderHistoryController extends Controller
{
    use ResolvesCustomerOrder;

    public function __invoke(string $order, CurrentCustomerContext $context, CustomerOrderHistory $history): JsonResponse
    {
        return ApiResponse::paginated($history->paginate($this->order($context, $order)));
    }
}
