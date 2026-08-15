<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Integrations\ListConfigurationRequest;
use App\Http\Requests\Api\V1\Integrations\StoreImportConfigurationRequest;
use App\Http\Requests\Api\V1\Integrations\UpdateImportConfigurationRequest;
use App\Http\Resources\Api\V1\Integrations\ImportConfigurationResource;
use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Actions\ManageImportConfigurationAction;
use App\Modules\Integrations\DTOs\CreateImportConfigurationData;
use App\Modules\Integrations\DTOs\UpdateImportConfigurationData;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Queries\IntegrationListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configurations d'import des clients.
 *
 * Aucun endpoint d'upload ni d'exécution : le §10 l'interdit, le diagramme
 * définit une configuration et rien d'autre.
 */
class ImportConfigurationController extends Controller
{
    use BuildsAuditContext;
    use ResolvesCustomerScope;

    /**
     * Lister les configurations d'import.
     *
     * Permission requise : `customer_import_configurations.view`.
     */
    public function index(ListConfigurationRequest $request, IntegrationListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [CustomerImportConfiguration::class, $organizationId]);

        return $this->respond($query->paginate('import', $request, $organizationId));
    }

    /**
     * Créer une configuration d'import.
     *
     * Permission requise : `customer_import_configurations.create`.
     */
    public function store(StoreImportConfigurationRequest $request, ManageImportConfigurationAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [CustomerImportConfiguration::class, $organizationId]);

        $configuration = $action->create(
            CreateImportConfigurationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ImportConfigurationResource($configuration));
    }

    /**
     * Consulter une configuration d'import.
     */
    public function show(Request $request, CustomerImportConfiguration $configuration): JsonResponse
    {
        $this->guardCustomerOwned($configuration);
        $this->authorize('view', $configuration);

        return ApiResponse::ok(new ImportConfigurationResource($configuration));
    }

    /**
     * Modifier une configuration d'import.
     *
     * Permission requise : `customer_import_configurations.update`.
     */
    public function update(
        UpdateImportConfigurationRequest $request,
        CustomerImportConfiguration $configuration,
        ManageImportConfigurationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCustomerOwned($configuration);
        $this->authorize('update', $configuration);

        $updated = $action->update(
            $configuration,
            UpdateImportConfigurationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new ImportConfigurationResource($updated));
    }

    /**
     * Supprimer une configuration d'import.
     *
     * @response 204
     */
    public function destroy(
        Request $request,
        CustomerImportConfiguration $configuration,
        ManageImportConfigurationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCustomerOwned($configuration);
        $this->authorize('delete', $configuration);

        $action->delete($configuration, $this->auditContext($request, $organizationId));

        return ApiResponse::noContent();
    }

    /**
     * Configurations d'import d'un client.
     */
    public function byCustomer(ListConfigurationRequest $request, Customer $customer, IntegrationListQuery $query): JsonResponse
    {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('viewAny', [CustomerImportConfiguration::class, $organizationId]);

        return $this->respond($query->paginate('import', $request, $organizationId, ['customer_id' => $customer->id]));
    }

    /**
     * Créer une configuration pour le client de l'URL.
     */
    public function storeForCustomer(
        StoreImportConfigurationRequest $request,
        Customer $customer,
        ManageImportConfigurationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('create', [CustomerImportConfiguration::class, $organizationId]);

        $configuration = $action->create(
            CreateImportConfigurationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ImportConfigurationResource($configuration));
    }

    private function respond(mixed $paginator): JsonResponse
    {
        return ApiResponse::paginated(
            $paginator->through(fn (CustomerImportConfiguration $c) => new ImportConfigurationResource($c)),
        );
    }
}
