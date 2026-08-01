<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Providers;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Providers\ListProviderRequest;
use App\Http\Requests\Api\V1\Providers\StoreProviderRequest;
use App\Http\Requests\Api\V1\Providers\UpdateProviderRequest;
use App\Http\Resources\Api\V1\Providers\ProviderDetailResource;
use App\Http\Resources\Api\V1\Providers\ProviderListResource;
use App\Modules\Providers\Actions\CreateProviderAction;
use App\Modules\Providers\Actions\DeleteProviderAction;
use App\Modules\Providers\Actions\UpdateProviderAction;
use App\Modules\Providers\DTOs\CreateProviderData;
use App\Modules\Providers\DTOs\UpdateProviderData;
use App\Modules\Providers\Exceptions\ProviderStillInUse;
use App\Modules\Providers\Models\Provider;
use App\Modules\Providers\Queries\ProviderListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fournisseurs de transport de l'organisation active.
 */
class ProviderController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les fournisseurs.
     *
     * Permission requise : `providers.view`. Recherche sur `code` et `name` ;
     * filtres `status`, `providerType`, `legacyId` ; tri autorisé sur `code`,
     * `name`, `provider_type`, `status`, `legacy_id`.
     */
    public function index(ListProviderRequest $request, ProviderListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Provider::class, $organizationId]);

        $paginator = $query->paginate($request, $organizationId);

        return ApiResponse::paginated($paginator->through(fn (Provider $p) => new ProviderListResource($p)));
    }

    /**
     * Créer un fournisseur.
     *
     * Permission requise : `providers.create`. Le fournisseur est rattaché à
     * l'organisation active ; le code y est unique.
     */
    public function store(StoreProviderRequest $request, CreateProviderAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Provider::class, $organizationId]);

        $provider = $action->execute(
            CreateProviderData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ProviderDetailResource($provider));
    }

    /**
     * Consulter un fournisseur.
     *
     * Permission requise : `providers.view`. Un fournisseur d'une autre
     * organisation renvoie 404.
     */
    public function show(Request $request, Provider $provider): JsonResponse
    {
        $this->guardScope($provider);
        $this->authorize('view', $provider);

        return ApiResponse::ok(new ProviderDetailResource(
            $provider->load('organization')->loadCount(['drivers', 'vehicles']),
        ));
    }

    /**
     * Modifier un fournisseur.
     *
     * Permission requise : `providers.update`. Le statut se change ici : il n'y
     * a pas d'endpoint dédié, faute de workflow de transitions.
     */
    public function update(UpdateProviderRequest $request, Provider $provider, UpdateProviderAction $action): JsonResponse
    {
        $organizationId = $this->guardScope($provider);
        $this->authorize('update', $provider);

        $updated = $action->execute(
            $provider,
            UpdateProviderData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new ProviderDetailResource($updated));
    }

    /**
     * Supprimer un fournisseur.
     *
     * Permission requise : `providers.delete`. Refusé en 409 s'il possède
     * encore des chauffeurs ou des véhicules — aucune cascade.
     *
     * @response 204
     */
    public function destroy(Request $request, Provider $provider, DeleteProviderAction $action): JsonResponse
    {
        $organizationId = $this->guardScope($provider);
        $this->authorize('delete', $provider);

        try {
            $action->execute($provider, $this->auditContext($request, $organizationId));
        } catch (ProviderStillInUse $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    private function guardScope(Provider $provider): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($provider->organization_id === $organizationId, 404, 'Fournisseur introuvable.');

        return $organizationId;
    }
}
