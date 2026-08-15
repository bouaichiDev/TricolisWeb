<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customers\UpdateCustomerRequest;
use App\Http\Requests\Api\V1\Customers\UpdateCustomerStatusRequest;
use App\Http\Resources\Api\V1\Customers\CustomerCompactResource;
use App\Http\Resources\Api\V1\Customers\CustomerResource;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Queries\CustomerListQuery;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Clients du transporteur, isolés par organisation active.
 */
class CustomerController extends Controller
{
    /**
     * Lister les clients de l'organisation active.
     *
     * Permission requise : `customers.view`. Recherche sur le code, le nom, la
     * raison sociale et l'email. Tri autorisé sur `name`, `code`, `created_at`.
     * `compact=1` renvoie la représentation réduite des listes déroulantes.
     */
    public function index(ListRequest $request, CustomerListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();

        $this->authorize('viewAny', [Customer::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        if ($request->wantsCompact()) {
            return ApiResponse::paginated($paginator->through(fn (Customer $customer): CustomerCompactResource => new CustomerCompactResource($customer)));
        }

        return ApiResponse::paginated($paginator->through(fn (Customer $customer): CustomerResource => new CustomerResource($customer)));
    }

    /**
     * Créer un client.
     *
     * Permission requise : `customers.create`. Le code doit être unique dans
     * l'organisation active, sinon la requête est rejetée en 422.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();

        $this->authorize('create', [Customer::class, $organizationId]);

        $validated = $request->validated();

        $code = $validated['code'];

        $this->validateUniqueCode($organizationId, $code);

        $customer = Customer::create([
            'organization_id' => $organizationId,
            'code' => $code,
            'name' => $validated['name'],
            'legal_name' => $validated['legalName'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'payment_mode' => $validated['paymentMode'] ?? null,
            'communication_mode' => $validated['communicationMode'] ?? null,
            'catalog_enabled' => $validated['catalogEnabled'] ?? false,
            'stock_enabled' => $validated['stockEnabled'] ?? false,
            'package_enabled' => $validated['packageEnabled'] ?? false,
            'appointment_enabled' => $validated['appointmentEnabled'] ?? false,
            'tracking_enabled' => $validated['trackingEnabled'] ?? false,
            'status' => $validated['status'] ?? CustomerStatus::ACTIVE->value,
        ]);
        $this->audit($request, $organizationId, 'created', $customer, null, $customer->toArray());

        return ApiResponse::created(new CustomerResource($customer));
    }

    /**
     * Consulter un client.
     *
     * Permission requise : `customers.view`. Un client d'une autre organisation
     * renvoie 403.
     */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return ApiResponse::ok(new CustomerResource($customer));
    }

    /**
     * Modifier un client.
     *
     * Permission requise : `customers.update`. Les capacités activables
     * (catalogue, stock, colis, rendez-vous, suivi) se pilotent ici.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);
        $oldValues = $customer->toArray();

        $validated = $request->validated();

        if (isset($validated['code']) && $validated['code'] !== $customer->code) {
            $this->validateUniqueCode($customer->organization_id, $validated['code'], $customer->id);
        }

        $data = [];

        foreach ([
            'code' => 'code',
            'name' => 'name',
            'legal_name' => 'legalName',
            'email' => 'email',
            'phone' => 'phone',
            'payment_mode' => 'paymentMode',
            'communication_mode' => 'communicationMode',
            'catalog_enabled' => 'catalogEnabled',
            'stock_enabled' => 'stockEnabled',
            'package_enabled' => 'packageEnabled',
            'appointment_enabled' => 'appointmentEnabled',
            'tracking_enabled' => 'trackingEnabled',
            'status' => 'status',
        ] as $db => $input) {
            if (array_key_exists($input, $validated)) {
                $data[$db] = $validated[$input];
            }
        }

        $customer->update($data);
        $this->audit($request, $customer->organization_id, 'updated', $customer, $oldValues, $customer->fresh()->toArray());

        return ApiResponse::ok(new CustomerResource($customer->fresh()));
    }

    /**
     * Supprimer un client.
     *
     * Permission requise : `customers.delete`. Un client possédant encore des
     * sites est refusé en 409.
     *
     * @response 204
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->sites()->exists()) {
            return ApiResponse::error('Impossible de supprimer un client possédant des sites.', 409);
        }
        $this->audit($request, $customer->organization_id, 'deleted', $customer, $customer->toArray(), null);
        $customer->delete();

        return ApiResponse::noContent();
    }

    /**
     * Changer le statut d'un client.
     *
     * Permission requise : `customers.update`, ou **`customers.block`** lorsque
     * le statut visé est `blocked`. Bloquer un client interrompt ses commandes ;
     * ce n'est pas une correction de fiche. Le changement est audité avec
     * l'ancien et le nouveau statut.
     */
    public function updateStatus(UpdateCustomerStatusRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize(
            $request->validated('status') === CustomerStatus::BLOCKED->value ? 'block' : 'update',
            $customer,
        );

        $oldValues = ['status' => $customer->status?->value];
        $customer->update(['status' => $request->validated('status')]);
        $this->audit($request, $customer->organization_id, 'status_changed', $customer, $oldValues, ['status' => $customer->status?->value]);

        return ApiResponse::ok(new CustomerResource($customer));
    }

    private function validateUniqueCode(string $organizationId, string $code, ?string $excludeId = null): void
    {
        $rule = Rule::unique('customers', 'code')
            ->where('organization_id', $organizationId);

        if ($excludeId !== null) {
            $rule->ignore($excludeId);
        }

        validator(['code' => $code], ['code' => ['required', 'string', $rule]])->validate();
    }
}
