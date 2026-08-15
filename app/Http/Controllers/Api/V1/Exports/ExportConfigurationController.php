<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Exports;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Integrations\ResolvesCustomerScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Exports\StoreExportConfigurationRequest;
use App\Http\Requests\Api\V1\Exports\UpdateExportConfigurationRequest;
use App\Http\Requests\Api\V1\Integrations\ListConfigurationRequest;
use App\Http\Resources\Api\V1\Exports\ExportConfigurationResource;
use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Actions\ManageExportConfigurationAction;
use App\Modules\Exports\DTOs\CreateExportConfigurationData;
use App\Modules\Exports\DTOs\UpdateExportConfigurationData;
use App\Modules\Exports\Exceptions\ExportConfigurationInUse;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Integrations\Queries\IntegrationListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configurations d'export des clients.
 *
 * Le mot de passe de transport n'est jamais restitué : seul `hasPassword`
 * indique qu'il est enregistré.
 */
class ExportConfigurationController extends Controller
{
    use BuildsAuditContext;
    use ResolvesCustomerScope;

    /**
     * Lister les configurations d'export.
     *
     * Permission requise : `customer_export_configurations.view`.
     */
    public function index(ListConfigurationRequest $request, IntegrationListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [CustomerExportConfiguration::class, $organizationId]);

        return $this->respond($query->paginate('export', $request, $organizationId));
    }

    /**
     * Créer une configuration d'export.
     *
     * Permission requise : `customer_export_configurations.create`. `host` est
     * exigé pour les transports FTP, SFTP et REST_API.
     */
    public function store(StoreExportConfigurationRequest $request, ManageExportConfigurationAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [CustomerExportConfiguration::class, $organizationId]);

        $configuration = $action->create(
            CreateExportConfigurationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ExportConfigurationResource($configuration));
    }

    /**
     * Consulter une configuration d'export.
     */
    public function show(Request $request, CustomerExportConfiguration $configuration): JsonResponse
    {
        $this->guardCustomerOwned($configuration);
        $this->authorize('view', $configuration);

        return ApiResponse::ok(new ExportConfigurationResource($configuration->loadCount('jobs')));
    }

    /**
     * Modifier une configuration d'export.
     *
     * Permission requise : `customer_export_configurations.update`. Omettre
     * `password` conserve l'ancien ; envoyer `null` l'efface.
     */
    public function update(
        UpdateExportConfigurationRequest $request,
        CustomerExportConfiguration $configuration,
        ManageExportConfigurationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCustomerOwned($configuration);
        $this->authorize('update', $configuration);

        $updated = $action->update(
            $configuration,
            UpdateExportConfigurationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new ExportConfigurationResource($updated));
    }

    /**
     * Supprimer une configuration d'export.
     *
     * Permission requise : `customer_export_configurations.delete`. Refusé en
     * 409 si des exports en découlent.
     *
     * @response 204
     */
    public function destroy(
        Request $request,
        CustomerExportConfiguration $configuration,
        ManageExportConfigurationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCustomerOwned($configuration);
        $this->authorize('delete', $configuration);

        try {
            $action->delete($configuration, $this->auditContext($request, $organizationId));
        } catch (ExportConfigurationInUse $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    /**
     * Configurations d'export d'un client.
     */
    public function byCustomer(ListConfigurationRequest $request, Customer $customer, IntegrationListQuery $query): JsonResponse
    {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('viewAny', [CustomerExportConfiguration::class, $organizationId]);

        return $this->respond($query->paginate('export', $request, $organizationId, ['customer_id' => $customer->id]));
    }

    /**
     * Créer une configuration pour le client de l'URL.
     */
    public function storeForCustomer(
        StoreExportConfigurationRequest $request,
        Customer $customer,
        ManageExportConfigurationAction $action,
    ): JsonResponse {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('create', [CustomerExportConfiguration::class, $organizationId]);

        $configuration = $action->create(
            CreateExportConfigurationData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ExportConfigurationResource($configuration));
    }

    private function respond(mixed $paginator): JsonResponse
    {
        return ApiResponse::paginated(
            $paginator->through(fn (CustomerExportConfiguration $c) => new ExportConfigurationResource($c)),
        );
    }
}
