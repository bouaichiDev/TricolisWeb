<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalogs\StoreCatalogItemRequest;
use App\Http\Requests\Api\V1\Catalogs\UpdateCatalogItemRequest;
use App\Http\Resources\Api\V1\Catalogs\CatalogItemResource;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\Customers\Models\Customer;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Articles d'un catalogue client.
 *
 * L'appartenance est vérifiée à chaque niveau : organisation active → client →
 * catalogue → article. Une rupture dans cette chaîne renvoie 404 plutôt que
 * 403, pour ne pas révéler l'existence de la ressource.
 */
class CustomerCatalogItemController extends Controller
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'article_code' => 'articleCode', 'barcode' => 'barcode', 'name' => 'name', 'description' => 'description',
        'weight' => 'weight', 'volume' => 'volume', 'length' => 'length', 'width' => 'width', 'height' => 'height', 'status' => 'status',
    ];

    /**
     * Lister les articles d'un catalogue.
     *
     * Permission requise : `catalogs.view`. Recherche sur le code article, le
     * code-barres et le nom ; filtre `status`.
     */
    public function index(ListRequest $request, Customer $customer, CustomerCatalog $catalog): JsonResponse
    {
        $this->guard($customer, $catalog);
        $this->authorize('view', $catalog);

        $query = $catalog->items();

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder->where('article_code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->getSort('article_code', ['article_code', 'name', 'created_at']);
        $paginator = $query->orderBy($sort, $request->getDirection())->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (CustomerCatalogItem $item) => new CatalogItemResource($item)));
    }

    /**
     * Ajouter un article au catalogue.
     *
     * Permission requise : `catalogs.update`. Le code article doit être unique
     * dans le catalogue.
     */
    public function store(StoreCatalogItemRequest $request, Customer $customer, CustomerCatalog $catalog): JsonResponse
    {
        $organizationId = $this->guard($customer, $catalog);
        $this->authorize('update', $catalog);

        $data = $request->validated();
        $this->assertUniqueArticleCode($catalog, $data['articleCode']);

        $item = $catalog->items()->create(InputMapper::map($data, self::MAPPING));
        $this->audit($request, $organizationId, 'created', $item, null, $item->toArray());

        return ApiResponse::created(new CatalogItemResource($item));
    }

    /**
     * Consulter un article.
     *
     * Permission requise : `catalogs.view`.
     */
    public function show(Request $request, Customer $customer, CustomerCatalog $catalog, CustomerCatalogItem $item): JsonResponse
    {
        $this->guard($customer, $catalog, $item);
        $this->authorize('view', $catalog);

        return ApiResponse::ok(new CatalogItemResource($item));
    }

    /**
     * Modifier un article.
     *
     * Permission requise : `catalogs.update`. Les commandes déjà passées ne sont
     * pas affectées : leurs lignes conservent une copie des données.
     */
    public function update(UpdateCatalogItemRequest $request, Customer $customer, CustomerCatalog $catalog, CustomerCatalogItem $item): JsonResponse
    {
        $organizationId = $this->guard($customer, $catalog, $item);
        $this->authorize('update', $catalog);

        $data = $request->validated();
        $old = $item->toArray();

        if (isset($data['articleCode']) && $data['articleCode'] !== $item->article_code) {
            $this->assertUniqueArticleCode($catalog, $data['articleCode']);
        }

        $item->update(InputMapper::map($data, self::MAPPING));
        $this->audit($request, $organizationId, 'updated', $item, $old, $item->fresh()->toArray());

        return ApiResponse::ok(new CatalogItemResource($item->fresh()));
    }

    /**
     * Supprimer un article.
     *
     * Permission requise : `catalogs.delete`. Un article référencé par une ligne
     * de commande est refusé en 409 : le désactiver est la bonne opération.
     *
     * @response 204
     */
    public function destroy(Request $request, Customer $customer, CustomerCatalog $catalog, CustomerCatalogItem $item): JsonResponse
    {
        $organizationId = $this->guard($customer, $catalog, $item);
        $this->authorize('delete', $catalog);

        if ($item->orderLines()->exists()) {
            return ApiResponse::error('Impossible de supprimer un article utilisé par une commande. Désactivez-le plutôt.', 409);
        }

        $this->audit($request, $organizationId, 'deleted', $item, $item->toArray(), null);
        $item->delete();

        return ApiResponse::noContent();
    }

    private function guard(Customer $customer, CustomerCatalog $catalog, ?CustomerCatalogItem $item = null): string
    {
        $organizationId = $this->requireOrganizationId();

        abort_unless($customer->organization_id === $organizationId, 404, 'Client introuvable.');
        abort_unless($catalog->customer_id === $customer->id, 404, 'Catalogue introuvable pour ce client.');
        abort_if($item !== null && $item->catalog_id !== $catalog->id, 404, 'Article introuvable dans ce catalogue.');

        return $organizationId;
    }

    private function assertUniqueArticleCode(CustomerCatalog $catalog, string $code): void
    {
        validator(['articleCode' => $code], [
            'articleCode' => [Rule::unique('customer_catalog_items', 'article_code')->where('catalog_id', $catalog->id)],
        ])->validate();
    }
}
