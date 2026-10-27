<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Catalog\ListCustomerCatalogItemsRequest;
use App\Http\Requests\Api\V1\Customer\Catalog\StoreCustomerCatalogItemRequest;
use App\Http\Resources\Api\V1\Customer\Catalog\CustomerCatalogItemResource;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\CustomerPortal\Actions\CreateCustomerCatalogItem;
use App\Modules\CustomerPortal\Services\Catalog\CustomerCatalogScope;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\Stock\Models\StockItem;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Le catalogue du client dans le portail : ses catalogues, leurs articles,
 * l'ajout d'un article. Ni modification ni suppression — aucune permission
 * du portail ne les prévoit ; elles restent au transporteur.
 */
class CustomerCatalogController extends Controller
{
    public function __construct(private readonly CustomerCatalogScope $scope) {}

    /** Les catalogues du client. Permission : `customer.catalog.view`. */
    public function catalogs(): JsonResponse
    {
        $catalogs = $this->scope->catalogs()->withCount('items')->orderBy('name')->get();

        return ApiResponse::ok($catalogs->map(static fn (CustomerCatalog $catalog): array => [
            'id' => $catalog->id,
            'code' => $catalog->code,
            'name' => $catalog->name,
            'status' => $catalog->status,
            'isActive' => $catalog->status === 'active',
            'itemsCount' => (int) $catalog->items_count,
        ])->values());
    }

    /** Les articles. Permissions : `customer.catalog.view` + `customer.articles.view`. */
    public function index(ListCustomerCatalogItemsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = ListCustomerCatalogItemsRequest::SORTS[$filters['sort'] ?? 'articleCode'];

        $page = $this->scope->items()
            ->with('catalog')
            ->when($filters['catalogId'] ?? null, static fn ($query, string $id) => $query->where('catalog_id', $id))
            ->when($filters['status'] ?? null, static fn ($query, string $status) => $query->where('status', $status))
            ->when($search !== '', static fn ($query) => $query->where(static fn ($builder) => $builder
                ->where('article_code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy($sort, $filters['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate((int) ($filters['perPage'] ?? 20));

        return ApiResponse::paginated($page->through(static fn (CustomerCatalogItem $item) => new CustomerCatalogItemResource($item)));
    }

    /** La fiche d'un article. Permission : `customer.articles.view`. */
    public function show(string $item, CurrentCustomerContext $context): JsonResponse
    {
        $article = $this->scope->item($item);

        return ApiResponse::ok(new CustomerCatalogItemResource($article, [
            'orderLines' => $article->orderLines()->whereHas('order', static fn ($order) => $order->where('customer_id', $context->customerId()))->count(),
            'stockItems' => StockItem::where('catalog_item_id', $article->id)->where('customer_id', $context->customerId())->count(),
        ]));
    }

    /** Ajouter un article. Permission : `customer.articles.create`. */
    public function store(StoreCustomerCatalogItemRequest $request, CreateCustomerCatalogItem $create): JsonResponse
    {
        $input = $request->validated();

        try {
            $catalog = $this->scope->activeCatalog((string) $input['catalogId']);
        } catch (ModelNotFoundException) {
            // Inactif, ou d'un autre client : la même réponse, sans dire lequel.
            throw ValidationException::withMessages(['catalogId' => ['Ce catalogue est introuvable ou inactif.']]);
        }

        $item = $create->execute($catalog, $input, $request);

        return ApiResponse::created(new CustomerCatalogItemResource($item->load('catalog')));
    }
}
