<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tracking\StoreTrackingEventDefinitionRequest;
use App\Http\Requests\Api\V1\Tracking\UpdateTrackingEventDefinitionRequest;
use App\Http\Resources\Api\V1\Tracking\TrackingEventDefinitionResource;
use App\Modules\Tracking\Models\TrackingEventDefinition;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le parcours client : quels statuts deviennent des étapes visibles.
 *
 * Le chauffeur pose un statut, l'étape apparaît — personne ne la saisit. C'est
 * ici qu'on décide *lesquels* comptent, sous quel titre et dans quel ordre.
 *
 * Distinct du référentiel des statuts, qui dit ce qu'un statut signifie en
 * interne : « in_progress » côté exploitation devient « Votre commande est en
 * route » côté client, et l'ordre du parcours est propre au parcours.
 */
class TrackingEventDefinitionController extends Controller
{
    /** Colonne de base de données → champ d'API, dans cet ordre. */
    private const array MAPPING = [
        'source_type' => 'sourceType',
        'status_code' => 'statusCode',
        'code' => 'code',
        'title' => 'title',
        'description' => 'description',
        'icon' => 'icon',
        'position' => 'position',
        'api_configuration_id' => 'apiConfigurationId',
        'service_id' => 'serviceId',
        'visible_to_customer' => 'visibleToCustomer',
        'shows_proof_of_delivery' => 'showsProofOfDelivery',
        'active' => 'active',
    ];

    /**
     * Lister les étapes du parcours.
     *
     * Permission requise : `tracking_event_definitions.view`. Triées par
     * `position` : c'est l'ordre du parcours, pas celui de la création.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [TrackingEventDefinition::class, $organizationId]);

        $query = TrackingEventDefinition::inOrganization($organizationId);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        return ApiResponse::paginated(
            // La prestation chargee avec l'etape : sans elle, l'ecran ne dirait
            // que des identifiants la ou il doit dire « Livraison ».
            $query->with('service:id,code,name')
                ->orderBy('position')->orderBy('code')->paginate($request->getPerPage())
                ->through(fn ($item) => new TrackingEventDefinitionResource($item)),
        );
    }

    /**
     * Ajouter une étape.
     *
     * Permission requise : `tracking_event_definitions.create`. Un couple
     * (entité, statut) ne peut porter qu'une étape : deux en produiraient deux
     * pour un seul changement, et rien ne dirait laquelle afficher.
     */
    public function store(StoreTrackingEventDefinitionRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [TrackingEventDefinition::class, $organizationId]);

        $definition = TrackingEventDefinition::create(
            InputMapper::map($request->validated(), self::MAPPING)
            + ['organization_id' => $organizationId],
        );

        $this->audit($request, $organizationId, 'created', $definition, null, $definition->toArray());

        return ApiResponse::created(new TrackingEventDefinitionResource($definition));
    }

    /** Consulter une étape. Permission : `tracking_event_definitions.view`. */
    public function show(TrackingEventDefinition $trackingEventDefinition): JsonResponse
    {
        $this->authorize('view', $trackingEventDefinition);

        return ApiResponse::ok(new TrackingEventDefinitionResource($trackingEventDefinition));
    }

    /**
     * Modifier une étape.
     *
     * Permission requise : `tracking_event_definitions.update`. Les événements
     * déjà publiés ne sont **pas** réécrits : ils portent ce qui était décrit au
     * moment où ils sont survenus.
     */
    public function update(
        UpdateTrackingEventDefinitionRequest $request,
        TrackingEventDefinition $trackingEventDefinition,
    ): JsonResponse {
        $this->authorize('update', $trackingEventDefinition);

        $old = $trackingEventDefinition->toArray();
        $trackingEventDefinition->update(InputMapper::map($request->validated(), self::MAPPING));

        $this->audit(
            $request,
            $trackingEventDefinition->organization_id,
            'updated',
            $trackingEventDefinition,
            $old,
            $trackingEventDefinition->fresh()->toArray(),
        );

        return ApiResponse::ok(new TrackingEventDefinitionResource($trackingEventDefinition->fresh()));
    }

    /**
     * Supprimer une étape.
     *
     * Permission requise : `tracking_event_definitions.delete`. Les événements
     * déjà survenus restent : ils constatent un fait, que retirer la
     * configuration ne défait pas. Désactiver l'étape suffit le plus souvent.
     *
     * @response 204
     */
    public function destroy(Request $request, TrackingEventDefinition $trackingEventDefinition): JsonResponse
    {
        $this->authorize('delete', $trackingEventDefinition);

        $this->audit(
            $request,
            $trackingEventDefinition->organization_id,
            'deleted',
            $trackingEventDefinition,
            $trackingEventDefinition->toArray(),
            null,
        );
        $trackingEventDefinition->delete();

        return ApiResponse::noContent();
    }
}
