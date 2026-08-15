<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Integrations\ListConfigurationRequest;
use App\Http\Requests\Api\V1\Integrations\StoreApiConfigurationRequest;
use App\Http\Requests\Api\V1\Integrations\UpdateApiConfigurationRequest;
use App\Http\Resources\Api\V1\Integrations\ApiConfigurationResource;
use App\Http\Resources\Api\V1\Integrations\ApiKeyIssuedResource;
use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Actions\CreateCustomerApiConfigurationAction;
use App\Modules\Integrations\Actions\ManageApiConfigurationAction;
use App\Modules\Integrations\Actions\RotateCustomerApiKeyAction;
use App\Modules\Integrations\DTOs\CreateApiConfigurationData;
use App\Modules\Integrations\DTOs\UpdateApiConfigurationData;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Queries\IntegrationListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Accès API des clients.
 *
 * La clé n'apparaît qu'à la création et à la rotation. Aucune lecture ne peut
 * la restituer : seule son empreinte SHA-256 est stockée.
 */
class ApiConfigurationController extends Controller
{
    use BuildsAuditContext;
    use ResolvesCustomerScope;

    /**
     * Lister les accès API.
     *
     * Permission requise : `customer_api_configurations.view`. `apiKeyHash`
     * n'est jamais exposé.
     */
    public function index(ListConfigurationRequest $request, IntegrationListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [CustomerApiConfiguration::class, $organizationId]);

        return $this->respond($query->paginate('api', $request, $organizationId));
    }

    /**
     * Créer un accès API et sa clé.
     *
     * Permission requise : `customer_api_configurations.create`. **La clé est
     * retournée une seule fois** : elle ne pourra pas être relue.
     */
    public function store(StoreApiConfigurationRequest $request, CreateCustomerApiConfigurationAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [CustomerApiConfiguration::class, $organizationId]);

        $issued = $action->execute(
            CreateApiConfigurationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ApiKeyIssuedResource($issued['configuration'], $issued['key']));
    }

    /**
     * Consulter un accès API.
     */
    public function show(Request $request, CustomerApiConfiguration $configuration): JsonResponse
    {
        $this->guardCustomerOwned($configuration);
        $this->authorize('view', $configuration);

        return ApiResponse::ok(new ApiConfigurationResource($configuration));
    }

    /**
     * Modifier un accès API.
     *
     * Permission requise : `customer_api_configurations.update`.
     */
    public function update(
        UpdateApiConfigurationRequest $request,
        CustomerApiConfiguration $configuration,
        ManageApiConfigurationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCustomerOwned($configuration);
        $this->authorize('update', $configuration);

        $updated = $action->update(
            $configuration,
            UpdateApiConfigurationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new ApiConfigurationResource($updated));
    }

    /**
     * Renouveler la clé d'un accès API.
     *
     * Permission requise : `customer_api_configurations.rotate_key` —
     * distincte d'`update` : l'ancienne clé cesse de fonctionner
     * immédiatement.
     */
    public function rotateKey(
        Request $request,
        CustomerApiConfiguration $configuration,
        RotateCustomerApiKeyAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCustomerOwned($configuration);
        $this->authorize('rotateKey', $configuration);

        $issued = $action->execute($configuration, $this->auditContext($request, $organizationId));

        return ApiResponse::ok(new ApiKeyIssuedResource($issued['configuration'], $issued['key']));
    }

    /**
     * Supprimer un accès API.
     *
     * @response 204
     */
    public function destroy(
        Request $request,
        CustomerApiConfiguration $configuration,
        ManageApiConfigurationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCustomerOwned($configuration);
        $this->authorize('delete', $configuration);

        $action->delete($configuration, $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }

    /**
     * Accès API d'un client.
     */
    public function byCustomer(ListConfigurationRequest $request, Customer $customer, IntegrationListQuery $query): JsonResponse
    {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('viewAny', [CustomerApiConfiguration::class, $organizationId]);

        return $this->respond($query->paginate('api', $request, $organizationId, ['customer_id' => $customer->id]));
    }

    /**
     * Créer un accès pour le client de l'URL.
     */
    public function storeForCustomer(
        StoreApiConfigurationRequest $request,
        Customer $customer,
        CreateCustomerApiConfigurationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('create', [CustomerApiConfiguration::class, $organizationId]);

        $issued = $action->execute(
            CreateApiConfigurationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ApiKeyIssuedResource($issued['configuration'], $issued['key']));
    }

    private function respond(mixed $paginator): JsonResponse
    {
        return ApiResponse::paginated(
            $paginator->through(fn (CustomerApiConfiguration $c) => new ApiConfigurationResource($c)),
        );
    }
}
