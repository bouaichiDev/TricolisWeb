<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Orders;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Orders\ListCustomerOrdersRequest;
use App\Http\Requests\Api\V1\Customer\Orders\StoreCustomerOrderRequest;
use App\Http\Requests\Api\V1\Customer\Orders\UpdateCustomerOrderRequest;
use App\Http\Resources\Api\V1\Customer\Orders\CustomerOrderListResource;
use App\Http\Resources\Api\V1\Customer\Orders\CustomerOrderResource;
use App\Modules\CustomerPortal\Actions\CreateCustomerOrder;
use App\Modules\CustomerPortal\Actions\UpdateCustomerOrder;
use App\Modules\CustomerPortal\Queries\CustomerOrderListQuery;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerOrderCapabilities;
use App\Modules\Orders\Models\Order;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Commandes du Customer courant.
 *
 * Chaque route passe par : authentification → Customer Context → permission
 * (`customer.permission:*` sur la route) → appartenance (`order()`) → règle
 * métier (`CustomerOrderCapabilities`, revérifiée par les actions).
 */
class CustomerOrderController extends Controller
{
    use ResolvesCustomerOrder;

    private const array DETAIL = [
        'lines',
        'packages.packageType',
        'packages.groupingType',
        'packages.packageOrderLines',
        'orderServices.service',
        'orderServices.address',
        'orderServices.contacts',
        'orderServices.servicePackages',
    ];

    public function __construct(
        private readonly CurrentCustomerContext $context,
        private readonly CustomerOrderCapabilities $capabilities,
    ) {}

    /**
     * Lister les commandes. Permission : `customer.orders.view`.
     */
    public function index(ListCustomerOrdersRequest $request, CustomerOrderListQuery $query): JsonResponse
    {
        $page = $query->paginate($this->context->customer(), $request->validated());
        $actions = $this->capabilities->forOrders($page->items());

        return ApiResponse::paginated(
            $page->through(static fn (Order $order) => new CustomerOrderListResource($order, $actions[$order->id]))
        );
    }

    /**
     * Consulter une commande. Permission : `customer.orders.view`.
     */
    public function show(string $order): JsonResponse
    {
        return $this->detail($this->order($this->context, $order));
    }

    /**
     * Créer une commande. Permission : `customer.orders.create`.
     */
    public function store(StoreCustomerOrderRequest $request, CreateCustomerOrder $create): JsonResponse
    {
        $order = $create->execute($request->validated(), $request);

        return $this->detail($order, 201);
    }

    /**
     * Modifier une commande. Permission : `customer.orders.update`.
     * `updatedAt` est obligatoire : 409 `ORDER_VERSION_CONFLICT` s'il a changé.
     */
    public function update(UpdateCustomerOrderRequest $request, string $order, UpdateCustomerOrder $update): JsonResponse
    {
        $updated = $update->execute($this->order($this->context, $order), $request->validated(), $request);

        return $this->detail($updated);
    }

    private function detail(Order $order, int $status = 200): JsonResponse
    {
        $order->load(self::DETAIL);
        $resource = new CustomerOrderResource($order, $this->capabilities->for($order));

        return $status === 201 ? ApiResponse::created($resource) : ApiResponse::ok($resource);
    }
}
