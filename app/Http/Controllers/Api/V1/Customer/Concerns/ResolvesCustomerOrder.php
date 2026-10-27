<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Concerns;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Retrouve une commande **dans le Customer courant**, et nulle part ailleurs.
 *
 * Pas de liaison implicite de route : elle chargerait la commande de n'importe
 * quel client avant tout contrôle. Ici la requête porte d'emblée
 * `customer_id` et `organization_id` ; la commande d'un autre client répond
 * exactement comme une commande inexistante — 404.
 */
trait ResolvesCustomerOrder
{
    protected function order(CurrentCustomerContext $context, string $id): Order
    {
        if (! Str::isUlid($id)) {
            throw (new ModelNotFoundException)->setModel(Order::class);
        }

        return Order::query()
            ->where('organization_id', $context->organizationId())
            ->where('customer_id', $context->customerId())
            ->findOrFail($id);
    }
}
