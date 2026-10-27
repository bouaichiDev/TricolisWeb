<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Orders;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\Orders\CustomerOrderLineResource;
use App\Http\Resources\Api\V1\Customer\Orders\CustomerOrderServiceResource;
use App\Http\Resources\Api\V1\Customer\Orders\CustomerPackageResource;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Le contenu d'une commande, par morceau — services, colis, lignes.
 * Permission : `customer.orders.view`. Toujours après `order()`, donc
 * toujours dans le Customer courant.
 */
class CustomerOrderContentController extends Controller
{
    use ResolvesCustomerOrder;

    public function __construct(private readonly CurrentCustomerContext $context) {}

    public function services(string $order): JsonResponse
    {
        $services = $this->order($this->context, $order)
            ->orderServices()
            ->with(['service', 'address', 'contacts', 'servicePackages'])
            ->orderBy('sequence')
            ->get();

        return ApiResponse::ok(CustomerOrderServiceResource::collection($services));
    }

    public function packages(string $order): JsonResponse
    {
        $packages = $this->order($this->context, $order)
            ->packages()
            ->with(['packageType', 'groupingType', 'packageOrderLines'])
            ->get();

        return ApiResponse::ok(CustomerPackageResource::collection($packages));
    }

    public function lines(string $order): JsonResponse
    {
        return ApiResponse::ok(CustomerOrderLineResource::collection($this->order($this->context, $order)->lines()->get()));
    }
}
