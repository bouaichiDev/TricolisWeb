<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Stock\ListCustomerStockRequest;
use App\Http\Requests\Api\V1\Customer\Stock\StoreCustomerStockItemRequest;
use App\Http\Requests\Api\V1\Customer\Stock\UpdateCustomerStockItemRequest;
use App\Http\Resources\Api\V1\Customer\Stock\CustomerStockBalanceResource;
use App\Http\Resources\Api\V1\Customer\Stock\CustomerStockItemResource;
use App\Http\Resources\Api\V1\Customer\Stock\CustomerStockLocation;
use App\Modules\CustomerPortal\Actions\CreateCustomerStockItem;
use App\Modules\CustomerPortal\Actions\UpdateCustomerStockItem;
use App\Modules\CustomerPortal\Queries\Stock\CustomerStockItemsQuery;
use App\Modules\CustomerPortal\Services\Stock\CustomerStockCapabilities;
use App\Modules\CustomerPortal\Services\Stock\CustomerStockScope;
use App\Modules\Statuses\Services\StatusMachine;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Shared\Database\MorphMap;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Articles en stock du Customer courant (§10-§14).
 *
 * Authentification → Customer Context → permission (`customer.stock.*`, sur
 * la route) → périmètre (`CustomerStockScope` : 404 hors du client).
 * Aucune route n'écrit une quantité : les balances se lisent, point.
 */
class CustomerStockItemController extends Controller
{
    public function __construct(
        private readonly CustomerStockScope $scope,
        private readonly CustomerStockItemsQuery $items,
        private readonly CustomerStockCapabilities $capabilities,
    ) {}

    /** Permission : `customer.stock.view`. Les options de filtre accompagnent la page. */
    public function index(ListCustomerStockRequest $request, StatusMachine $statuses): JsonResponse
    {
        $actions = $this->capabilities->forItem();
        $page = $this->items->paginate($request->validated());
        $response = ApiResponse::paginated($page->through(static fn (StockItem $item) => new CustomerStockItemResource($item, $actions)));

        // Seuls les emplacements où le client a du stock : jamais la carte du dépôt.
        $locations = StockLocation::query()->with('depot:id,name')
            ->whereIn('id', $this->scope->balances()->select('stock_location_id'))
            ->orderBy('location_code')->get();

        $body = $response->getData(true);
        $body['meta']['filters'] = [
            'statuses' => $statuses->statuses(MorphMap::STOCK_ITEM)->where('active', true)
                ->map(static fn ($status): array => ['code' => $status->code, 'label' => $status->label])->values(),
            'locations' => $locations->map(static fn (StockLocation $location): ?array => CustomerStockLocation::present($location))->values(),
        ];

        return $response->setData($body);
    }

    /** Permission : `customer.stock.view`. */
    public function show(string $stockItem): JsonResponse
    {
        return $this->detail($stockItem);
    }

    /** Permission : `customer.stock.items.create`. Aucune balance ni mouvement créés. */
    public function store(StoreCustomerStockItemRequest $request, CreateCustomerStockItem $create): JsonResponse
    {
        return $this->detail($create->execute($request->validated(), $request)->id, 201);
    }

    /** Permission : `customer.stock.items.update`. Métadonnées seulement. */
    public function update(UpdateCustomerStockItemRequest $request, string $stockItem, UpdateCustomerStockItem $update): JsonResponse
    {
        $item = $this->scope->find($this->scope->items(), $stockItem);

        return $this->detail($update->execute($item, $request->validated(), $request)->id);
    }

    /** Permission : `customer.stock.view`. La répartition par emplacement, en lecture seule. */
    public function balances(string $stockItem): JsonResponse
    {
        $item = $this->scope->find($this->scope->items(), $stockItem);
        $balances = $item->balances()->with(['stockLocation:id,depot_id,zone_code,aisle,rack,level,location_code', 'stockLocation.depot:id,name'])
            ->orderBy('stock_location_id')->get();

        return ApiResponse::ok(CustomerStockBalanceResource::collection($balances));
    }

    private function detail(string $id, int $status = 200): JsonResponse
    {
        $item = $this->scope->find($this->items->withTotals()->with('catalogItem:id,article_code,name'), $id);
        $resource = new CustomerStockItemResource($item, $this->capabilities->forItem());

        return $status === 201 ? ApiResponse::created($resource) : ApiResponse::ok($resource);
    }
}
