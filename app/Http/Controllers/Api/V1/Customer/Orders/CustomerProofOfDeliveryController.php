<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Orders;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerOrderService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\Orders\CustomerProofOfDeliveryResource;
use App\Modules\CustomerPortal\Queries\CustomerProofsOfDelivery;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Preuves de livraison des commandes du client — lecture seule (§18).
 *
 * Permission : `customer.orders.proofs_of_delivery.view`. Toute lecture part
 * de `CustomerProofsOfDelivery` : `POD → Order → Customer courant`, service
 * cohérent avec la commande. Aucune route d'écriture n'existe.
 */
class CustomerProofOfDeliveryController extends Controller
{
    use ResolvesCustomerOrderService;

    private const array RELATIONS = [
        'orderService:id,order_id,service_id,service_number',
        'orderService.service:id,name',
        'signatureDocument',
        'photoDocument',
    ];

    public function __construct(private readonly CurrentCustomerContext $context) {}

    public function byOrder(string $order): JsonResponse
    {
        $resolved = $this->order($this->context, $order);

        return $this->list($this->proofs()->where('order_id', $resolved->id));
    }

    public function byService(string $order, string $service): JsonResponse
    {
        [$resolvedOrder, $resolvedService] = $this->orderService($this->context, $order, $service);

        return $this->list($this->proofs()->where('order_id', $resolvedOrder->id)->where('order_service_id', $resolvedService->id));
    }

    public function show(string $proofOfDelivery): JsonResponse
    {
        if (! Str::isUlid($proofOfDelivery)) {
            throw (new ModelNotFoundException)->setModel(ProofOfDelivery::class);
        }

        $proof = $this->proofs()->with(self::RELATIONS)->findOrFail($proofOfDelivery);

        return ApiResponse::ok(new CustomerProofOfDeliveryResource($proof));
    }

    /**
     * @return Builder<ProofOfDelivery>
     */
    private function proofs(): Builder
    {
        return CustomerProofsOfDelivery::query($this->context->customer());
    }

    /**
     * @param  Builder<ProofOfDelivery>  $query
     */
    private function list(Builder $query): JsonResponse
    {
        $proofs = $query->with(self::RELATIONS)->latest('delivered_at')->get();

        return ApiResponse::ok(CustomerProofOfDeliveryResource::collection($proofs));
    }
}
