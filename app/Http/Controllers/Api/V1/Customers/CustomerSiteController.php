<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\StoreCustomerSiteRequest;
use App\Http\Requests\Api\V1\Customers\UpdateCustomerSiteRequest;
use App\Http\Resources\Api\V1\Customers\CustomerSiteResource;
use App\Modules\Addresses\Models\Address;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerSite;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sites d'un client (`Customer 1 — 0..* CustomerSite`, `Address 1 — 0..* CustomerSite`).
 */
class CustomerSiteController extends Controller
{
    /**
     * Lister les sites d'un client.
     *
     * Permission requise : `customer_sites.view`. Filtre `status`, tri autorisé
     * sur `name`, `code`, `created_at`.
     */
    public function index(ListRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        $this->authorize('viewAny', [CustomerSite::class, $customer]);

        $query = CustomerSite::where('customer_id', $customer->id);

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->getSort('name', ['name', 'code', 'created_at']);
        $direction = $request->getDirection();

        $paginator = $query->orderBy($sort, $direction)->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (CustomerSite $site): CustomerSiteResource => new CustomerSiteResource($site)));
    }

    /**
     * Créer un site client.
     *
     * Permission requise : `customer_sites.create`. L'adresse doit exister et
     * être visible dans l'organisation active, sinon 403.
     */
    public function store(StoreCustomerSiteRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        $this->authorize('create', [CustomerSite::class, $customer]);

        $validated = $request->validated();

        $address = Address::findOrFail($validated['addressId']);
        $this->authorize('view', $address);

        $site = CustomerSite::create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'site_type' => $validated['siteType'] ?? null,
            'is_default' => $validated['isDefault'] ?? false,
            'status' => $validated['status'] ?? 'active',
        ]);
        $this->audit($request, $customer->organization_id, 'created', $site, null, $site->toArray());

        return ApiResponse::created(new CustomerSiteResource($site));
    }

    /**
     * Consulter un site client.
     *
     * Permission requise : `customer_sites.view`. Un site appartenant à un autre
     * client renvoie 404.
     */
    public function show(Request $request, Customer $customer, CustomerSite $site): JsonResponse
    {
        $this->authorize('view', $customer);
        $this->authorize('view', $site);
        $this->ensureSiteBelongsToCustomer($customer, $site);

        return ApiResponse::ok(new CustomerSiteResource($site));
    }

    /**
     * Modifier un site client.
     *
     * Permission requise : `customer_sites.update`. Toute modification est auditée.
     */
    public function update(UpdateCustomerSiteRequest $request, Customer $customer, CustomerSite $site): JsonResponse
    {
        $this->authorize('view', $customer);
        $this->authorize('update', $site);
        $this->ensureSiteBelongsToCustomer($customer, $site);
        $oldValues = $site->toArray();

        $validated = $request->validated();

        if (isset($validated['addressId'])) {
            $address = Address::findOrFail($validated['addressId']);
            $this->authorize('view', $address);
        }

        $data = [];

        foreach ([
            'address_id' => 'addressId',
            'code' => 'code',
            'name' => 'name',
            'site_type' => 'siteType',
            'is_default' => 'isDefault',
            'status' => 'status',
        ] as $db => $input) {
            if (array_key_exists($input, $validated)) {
                $data[$db] = $validated[$input];
            }
        }

        $site->update($data);
        $this->audit($request, $customer->organization_id, 'updated', $site, $oldValues, $site->fresh()->toArray());

        return ApiResponse::ok(new CustomerSiteResource($site->fresh()));
    }

    /**
     * Supprimer un site client.
     *
     * Permission requise : `customer_sites.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Customer $customer, CustomerSite $site): JsonResponse
    {
        $this->authorize('view', $customer);
        $this->authorize('delete', $site);
        $this->ensureSiteBelongsToCustomer($customer, $site);

        $this->audit($request, $customer->organization_id, 'deleted', $site, $site->toArray(), null);
        $site->delete();

        return ApiResponse::noContent();
    }

    /**
     * Un site appartient à un seul client : le site de l'URL doit être celui du
     * client de l'URL, sinon la ressource est considérée introuvable.
     */
    private function ensureSiteBelongsToCustomer(Customer $customer, CustomerSite $site): void
    {
        abort_unless($site->customer_id === $customer->id, 404, 'Site introuvable pour ce client.');
    }
}
