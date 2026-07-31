<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalogs\StoreCatalogRequest;
use App\Http\Requests\Api\V1\Catalogs\UpdateCatalogRequest;
use App\Http\Resources\Api\V1\Catalogs\CatalogResource;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Customers\Models\Customer;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catalogues d'un client (`Customer 1 — 0..* CustomerCatalog`).
 *
 * Le catalogue est facultatif : un client peut commander sans, avec des lignes
 * saisies manuellement. Il décrit les articles, jamais des quantités
 * disponibles — celles-ci relèvent du module Stock.
 */
class CustomerCatalogController extends Controller
{
    /** @var array<string, string> */
    private const array MAPPING = ['code' => 'code', 'name' => 'name', 'description' => 'description', 'status' => 'status'];

    /**
     * Lister les catalogues d'un client.
     *
     * Permission requise : `catalogs.view`. Recherche sur le code et le nom,
     * filtre `status`, tri autorisé sur `code`, `name`, `created_at`.
     */
    public function index(ListRequest $request, Customer $customer): JsonResponse
    {
        $this->guardCustomer($customer);
        $this->authorize('viewAny', [CustomerCatalog::class, $customer]);

        $query = CustomerCatalog::where('customer_id', $customer->id)->withCount('items');

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->getSort('code', ['code', 'name', 'created_at']);
        $paginator = $query->orderBy($sort, $request->getDirection())->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (CustomerCatalog $catalog) => new CatalogResource($catalog)));
    }

    /**
     * Créer un catalogue.
     *
     * Permission requise : `catalogs.create`. Le code doit être unique chez ce
     * client.
     */
    public function store(StoreCatalogRequest $request, Customer $customer): JsonResponse
    {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('create', [CustomerCatalog::class, $customer]);

        $data = $request->validated();
        $this->assertUniqueCode($customer, $data['code']);

        $catalog = $customer->catalogs()->create(InputMapper::map($data, self::MAPPING));
        $this->audit($request, $organizationId, 'created', $catalog, null, $catalog->toArray());

        return ApiResponse::created(new CatalogResource($catalog));
    }

    /**
     * Consulter un catalogue.
     *
     * Permission requise : `catalogs.view`. Un catalogue appartenant à un autre
     * client renvoie 404.
     */
    public function show(Request $request, Customer $customer, CustomerCatalog $catalog): JsonResponse
    {
        $this->guardCustomer($customer);
        $this->authorize('view', $catalog);
        $this->assertBelongsToCustomer($customer, $catalog);

        return ApiResponse::ok(new CatalogResource($catalog->loadCount('items')));
    }

    /**
     * Modifier un catalogue.
     *
     * Permission requise : `catalogs.update`. Désactiver un catalogue empêche
     * d'y puiser de nouvelles lignes de commande, sans toucher aux commandes
     * déjà passées.
     */
    public function update(UpdateCatalogRequest $request, Customer $customer, CustomerCatalog $catalog): JsonResponse
    {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('update', $catalog);
        $this->assertBelongsToCustomer($customer, $catalog);

        $data = $request->validated();
        $old = $catalog->toArray();

        if (isset($data['code']) && $data['code'] !== $catalog->code) {
            $this->assertUniqueCode($customer, $data['code']);
        }

        $catalog->update(InputMapper::map($data, self::MAPPING));
        $this->audit($request, $organizationId, 'updated', $catalog, $old, $catalog->fresh()->toArray());

        return ApiResponse::ok(new CatalogResource($catalog->fresh()));
    }

    /**
     * Supprimer un catalogue.
     *
     * Permission requise : `catalogs.delete`. Un catalogue dont un article est
     * encore référencé par une commande est refusé en 409 : la contrainte
     * `RESTRICT` protège l'historique.
     *
     * @response 204
     */
    public function destroy(Request $request, Customer $customer, CustomerCatalog $catalog): JsonResponse
    {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('delete', $catalog);
        $this->assertBelongsToCustomer($customer, $catalog);

        $used = $catalog->items()->whereHas('orderLines')->exists();

        if ($used) {
            return ApiResponse::error('Impossible de supprimer un catalogue dont des articles sont utilisés par des commandes.', 409);
        }

        $this->audit($request, $organizationId, 'deleted', $catalog, $catalog->toArray(), null);
        $catalog->delete();

        return ApiResponse::noContent();
    }

    private function guardCustomer(Customer $customer): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($customer->organization_id === $organizationId, 404, 'Client introuvable.');

        return $organizationId;
    }

    private function assertBelongsToCustomer(Customer $customer, CustomerCatalog $catalog): void
    {
        abort_unless($catalog->customer_id === $customer->id, 404, 'Catalogue introuvable pour ce client.');
    }

    private function assertUniqueCode(Customer $customer, string $code): void
    {
        validator(['code' => $code], [
            'code' => [Rule::unique('customer_catalogs', 'code')->where('customer_id', $customer->id)],
        ])->validate();
    }
}
