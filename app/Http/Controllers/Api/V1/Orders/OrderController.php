<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\StoreOrderRequest;
use App\Http\Resources\Api\V1\Orders\OrderResource;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Commandes, lignes de commande et services associés.
 */
class OrderController extends Controller
{
    /**
     * Lister les commandes de l'organisation active.
     *
     * Permission requise : `orders.view`. Recherche sur `order_number` et
     * `customer_reference`, filtre `status`, tri par date de commande décroissante.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('viewAny', [Order::class, $org]);
        $query = Order::where('organization_id', $org)->with(['lines', 'orderServices']);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder->where('order_number', 'like', "%$search%")->orWhere('customer_reference', 'like', "%$search%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $paginator = $query->latest('order_date')->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (Order $order) => new OrderResource($order)));
    }

    /**
     * Créer une commande avec ses lignes et ses services.
     *
     * Permission requise : `orders.create`. Le client, l'agence, le dépôt, les
     * services et les adresses doivent tous appartenir à l'organisation active,
     * sinon 422. La commande, ses lignes et ses services sont créés dans une
     * seule transaction.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $org = $this->requireOrganizationId();
        $this->authorize('create', [Order::class, $org]);
        $v = $request->validated();
        validator($v, ['orderNumber' => [Rule::unique('orders', 'order_number')->where('organization_id', $org)], 'customerId' => [Rule::exists('customers', 'id')->where('organization_id', $org)], 'agencyId' => [Rule::exists('agencies', 'id')->where('organization_id', $org)]])->validate();
        if (isset($v['depotId']) && ! Depot::whereKey($v['depotId'])->whereHas('agency', fn ($q) => $q->where('organization_id', $org))->exists()) {
            abort(422, 'Dépôt invalide.');
        }
        foreach ($v['services'] as $index => $service) {
            validator($service, [
                'serviceId' => [Rule::exists(Service::class, 'id')->where('organization_id', $org)],
                'addressId' => [Rule::exists(EntityAddress::class, 'address_id')->where('organization_id', $org)],
            ], [], [
                'serviceId' => "services.$index.serviceId",
                'addressId' => "services.$index.addressId",
            ])->validate();
        }
        $order = DB::transaction(function () use ($v, $org, $request) {
            $o = Order::create(['organization_id' => $org, 'customer_id' => $v['customerId'], 'agency_id' => $v['agencyId'], 'depot_id' => $v['depotId'] ?? null, 'order_number' => $v['orderNumber'], 'external_reference' => $v['externalReference'] ?? null, 'customer_reference' => $v['customerReference'] ?? null, 'order_type' => $v['orderType'] ?? null, 'order_date' => $v['orderDate'], 'source' => $v['source'] ?? OrderSource::INTERNAL->value, 'currency_code' => $v['currencyCode'] ?? 'MAD', 'status' => $v['status'] ?? OrderStatus::DRAFT->value, 'internal_remark' => $v['internalRemark'] ?? null, 'worker_remark' => $v['workerRemark'] ?? null, 'created_by' => $request->user()->id]);
            foreach ($v['lines'] as $l) {
                $o->lines()->create(['name' => $l['name'], 'article_code' => $l['articleCode'] ?? null, 'barcode' => $l['barcode'] ?? null, 'description' => $l['description'] ?? null, 'quantity' => $l['quantity'], 'weight' => $l['weight'] ?? 0, 'volume' => $l['volume'] ?? 0, 'selling_price' => $l['sellingPrice'] ?? null]);
            }
            foreach ($v['services'] as $service) {
                $o->orderServices()->create([
                    'service_id' => $service['serviceId'], 'address_id' => $service['addressId'], 'service_number' => $service['serviceNumber'], 'sequence' => $service['sequence'], 'requested_date' => $service['requestedDate'], 'requested_from' => $service['requestedFrom'] ?? null, 'requested_to' => $service['requestedTo'] ?? null, 'quantity' => $service['quantity'], 'unit' => $service['unit'], 'required_time_minutes' => $service['requiredTimeMinutes'], 'remaining_time_minutes' => $service['remainingTimeMinutes'], 'weight' => $service['weight'], 'volume' => $service['volume'], 'package_count' => $service['packageCount'], 'customer_unit_price' => $service['customerUnitPrice'], 'customer_total_price' => $service['customerTotalPrice'], 'provider_unit_cost' => $service['providerUnitCost'], 'provider_total_cost' => $service['providerTotalCost'], 'instructions' => $service['instructions'] ?? null, 'status' => $service['status'],
                ]);
            }

            return $o;
        });

        $this->audit($request, $org, 'created', $order, null, $order->load(['lines', 'orderServices'])->toArray());

        return ApiResponse::created(new OrderResource($order->load(['lines', 'orderServices'])));
    }

    /**
     * Consulter une commande, ses lignes et ses services.
     *
     * Permission requise : `orders.view`.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return ApiResponse::ok(new OrderResource($order->load(['lines', 'orderServices'])));
    }

    /**
     * Supprimer une commande.
     *
     * Permission requise : `orders.delete`. L'état complet est journalisé avant
     * suppression.
     *
     * @response 204
     */
    public function destroy(Request $request, Order $order): JsonResponse
    {
        $this->authorize('delete', $order);
        $this->audit($request, $order->organization_id, 'deleted', $order, $order->load(['lines', 'orderServices'])->toArray(), null);
        $order->delete();

        return ApiResponse::noContent();
    }
}
