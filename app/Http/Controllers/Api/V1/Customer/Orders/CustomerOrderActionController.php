<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Orders;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Orders\CancelCustomerOrderRequest;
use App\Modules\CustomerPortal\Actions\CancelCustomerOrder;
use App\Modules\CustomerPortal\Actions\DuplicateCustomerOrder;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerOrderCapabilities;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Duplication et annulation : deux gestes qui ne modifient pas le contenu.
 */
class CustomerOrderActionController extends Controller
{
    use ResolvesCustomerOrder;

    public function __construct(private readonly CurrentCustomerContext $context) {}

    /**
     * Dupliquer une commande. Permission : `customer.orders.duplicate`.
     * Répond la nouvelle commande, pour que le portail y redirige.
     */
    public function duplicate(Request $request, string $order, DuplicateCustomerOrder $duplicate): JsonResponse
    {
        $copy = $duplicate->execute($this->order($this->context, $order), $request);

        return ApiResponse::created(['id' => $copy->id, 'reference' => $copy->order_number]);
    }

    /**
     * Annuler une commande. Permission : `customer.orders.cancel`.
     * 409 `ORDER_NOT_CANCELLABLE` quand la règle métier le refuse.
     */
    public function cancel(
        CancelCustomerOrderRequest $request,
        string $order,
        CancelCustomerOrder $cancel,
        CustomerOrderCapabilities $capabilities,
    ): JsonResponse {
        $cancelled = $cancel->execute($this->order($this->context, $order), $request->validated('reason'), $request);

        return ApiResponse::ok([
            'id' => $cancelled->id,
            'status' => $cancelled->status->value,
            'actions' => $capabilities->for($cancelled),
        ]);
    }
}
