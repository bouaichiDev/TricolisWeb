<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Concerns;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Retrouve une prestation **de la commande** du Customer courant.
 *
 * La commande est d'abord cherchée dans le client (404 sinon), puis la
 * prestation dans cette commande : un service d'une autre commande — même du
 * même client — répond 404. Le chemin `Customer → Order → OrderService` n'est
 * jamais raccourci.
 */
trait ResolvesCustomerOrderService
{
    use ResolvesCustomerOrder;

    /**
     * @return array{0: Order, 1: OrderService}
     */
    protected function orderService(CurrentCustomerContext $context, string $orderId, string $serviceId): array
    {
        $order = $this->order($context, $orderId);

        if (! Str::isUlid($serviceId)) {
            throw (new ModelNotFoundException)->setModel(OrderService::class);
        }

        return [$order, OrderService::query()->where('order_id', $order->id)->findOrFail($serviceId)];
    }
}
