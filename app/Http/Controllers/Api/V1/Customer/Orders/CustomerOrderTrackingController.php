<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Orders;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerOrder;
use App\Http\Controllers\Controller;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\Tracking\CustomerTrackingPresenter;
use App\Modules\CustomerPortal\Services\Tracking\OrderTrackingBuilder;
use App\Modules\Orders\Models\Order;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Suivi d'une commande du Customer courant (§11).
 *
 * Authentification → Customer Context → `customer.tracking.view` (route) →
 * appartenance (`order()`, 404 pour la commande d'un autre client) →
 * `customer.orders.track`. Une commande encore en brouillon répond
 * proprement `available: false` : il n'y a rien à suivre, ce n'est pas une
 * erreur.
 */
class CustomerOrderTrackingController extends Controller
{
    use ResolvesCustomerOrder;

    public function __construct(
        private readonly CurrentCustomerContext $context,
        private readonly OrderTrackingBuilder $builder,
        private readonly CustomerTrackingPresenter $presenter,
    ) {}

    public function show(string $order): JsonResponse
    {
        $resolved = $this->trackable($order);

        return ApiResponse::ok($this->presenter->present($this->builder->build($resolved)));
    }

    /**
     * Le suivi d'une seule prestation de la commande.
     */
    public function service(string $order, string $service): JsonResponse
    {
        $snapshot = $this->builder->build($this->trackable($order));
        $tracked = Str::isUlid($service) ? $snapshot->service($service) : null;

        if ($tracked === null) {
            throw (new ModelNotFoundException)->setModel(Order::class);
        }

        return ApiResponse::ok($this->presenter->presentService($snapshot, $tracked));
    }

    private function trackable(string $id): Order
    {
        abort_unless($this->context->can('customer.orders.track'), 403, 'Vous ne pouvez pas suivre les commandes de votre entreprise.');

        return $this->order($this->context, $id);
    }
}
