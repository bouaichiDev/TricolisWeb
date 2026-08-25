<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Integrations\StoreOrganizationApiConfigurationRequest;
use App\Http\Requests\Api\V1\Integrations\UpdateOrganizationApiConfigurationRequest;
use App\Http\Resources\Api\V1\Integrations\OrganizationApiConfigurationResource;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Les API externes qu'un organisme appelle.
 *
 * Sens inverse d'`ApiConfigurationController`, où un **client** détient une clé
 * pour nous appeler. Ici, c'est nous qui appelons — la position d'un chauffeur,
 * rendue par le système de télématique de l'organisme.
 *
 * **Le secret ne ressort jamais.** Il s'écrit et se remplace ; aucune route ne
 * le relit. Le champ `credentials` n'a donc pas de pendant en lecture, et c'est
 * volontaire : un secret consultable finit copié ailleurs.
 */
class OrganizationApiConfigurationController extends Controller
{
    /** Colonne de base de données → champ d'API, dans cet ordre. */
    private const array MAPPING = [
        'code' => 'code',
        'name' => 'name',
        'base_url' => 'baseUrl',
        'auth_type' => 'authType',
        'headers' => 'headers',
        'timeout_seconds' => 'timeoutSeconds',
        'is_active' => 'isActive',
    ];

    /**
     * Lister les API externes de l'organisation.
     *
     * Permission requise : `api_configurations.view`.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [OrganizationApiConfiguration::class, $organizationId]);

        $query = OrganizationApiConfiguration::inOrganization($organizationId);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        return ApiResponse::paginated(
            $query->orderBy('name')->paginate($request->getPerPage())
                ->through(fn ($item) => new OrganizationApiConfigurationResource($item)),
        );
    }

    /**
     * Déclarer une API externe.
     *
     * Permission requise : `api_configurations.create`. L'adresse doit être en
     * HTTPS : un secret envoyé en clair n'en est plus un.
     */
    public function store(StoreOrganizationApiConfigurationRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [OrganizationApiConfiguration::class, $organizationId]);

        $data = $request->validated();

        $configuration = new OrganizationApiConfiguration(
            InputMapper::map($data, self::MAPPING) + ['organization_id' => $organizationId],
        );
        $configuration->setCredentials($data['credentials'] ?? null);
        $configuration->save();

        $this->audit($request, $organizationId, 'created', $configuration, null, $this->safe($configuration));

        return ApiResponse::created(new OrganizationApiConfigurationResource($configuration));
    }

    /** Consulter une API externe. Permission requise : `api_configurations.view`. */
    public function show(OrganizationApiConfiguration $apiConfiguration): JsonResponse
    {
        $this->authorize('view', $apiConfiguration);

        return ApiResponse::ok(new OrganizationApiConfigurationResource($apiConfiguration));
    }

    /**
     * Modifier une API externe.
     *
     * Permission requise : `api_configurations.update`. Omettre `credentials`
     * conserve le secret en place ; l'envoyer à `null` l'efface.
     */
    public function update(
        UpdateOrganizationApiConfigurationRequest $request,
        OrganizationApiConfiguration $apiConfiguration,
    ): JsonResponse {
        $this->authorize('update', $apiConfiguration);

        $data = $request->validated();
        $before = $this->safe($apiConfiguration);

        $apiConfiguration->fill(InputMapper::map($data, self::MAPPING));

        if (array_key_exists('credentials', $data)) {
            $apiConfiguration->setCredentials($data['credentials']);
        }

        $apiConfiguration->save();

        $this->audit(
            $request,
            $apiConfiguration->organization_id,
            'updated',
            $apiConfiguration,
            $before,
            $this->safe($apiConfiguration),
        );

        return ApiResponse::ok(new OrganizationApiConfigurationResource($apiConfiguration->fresh()));
    }

    /**
     * Supprimer une API externe.
     *
     * Permission requise : `api_configurations.delete`. Les étapes du parcours
     * qui s'y référaient restent, sans suivi en direct — `nullOnDelete`.
     *
     * @response 204
     */
    public function destroy(Request $request, OrganizationApiConfiguration $apiConfiguration): JsonResponse
    {
        $this->authorize('delete', $apiConfiguration);

        $this->audit(
            $request,
            $apiConfiguration->organization_id,
            'deleted',
            $apiConfiguration,
            $this->safe($apiConfiguration),
            null,
        );
        $apiConfiguration->delete();

        return ApiResponse::noContent();
    }

    /**
     * Instantané pour le journal d'audit, **sans le secret**.
     *
     * `toArray()` le porterait : le journal est lu, exporté, conservé. Un
     * secret qui y entre en ressort un jour.
     *
     * @return array<string, mixed>
     */
    private function safe(OrganizationApiConfiguration $configuration): array
    {
        return $configuration->only([
            'code', 'name', 'base_url', 'auth_type', 'timeout_seconds', 'is_active',
        ]);
    }
}
