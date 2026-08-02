<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Communications\ListCommunicationRequest;
use App\Http\Requests\Api\V1\Communications\StoreCommunicationTemplateRequest;
use App\Http\Requests\Api\V1\Communications\UpdateCommunicationTemplateRequest;
use App\Http\Resources\Api\V1\Communications\CommunicationTemplateDetailResource;
use App\Http\Resources\Api\V1\Communications\CommunicationTemplateListResource;
use App\Modules\Communications\Actions\ManageCommunicationTemplateAction;
use App\Modules\Communications\DTOs\CreateCommunicationTemplateData;
use App\Modules\Communications\DTOs\UpdateCommunicationTemplateData;
use App\Modules\Communications\Exceptions\CommunicationTemplateInUse;
use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Communications\Queries\CommunicationListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Modèles de message.
 */
class CommunicationTemplateController extends Controller
{
    use BuildsAuditContext;
    use ResolvesCommunicationScope;

    /**
     * Lister les modèles de message.
     *
     * Permission requise : `communication_templates.view`. La recherche porte
     * sur le code, le nom, l'objet et le corps.
     */
    public function index(ListCommunicationRequest $request, CommunicationListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [CommunicationTemplate::class, $organizationId]);

        return ApiResponse::paginated(
            $query->paginate('template', $request, $organizationId)
                ->through(fn (CommunicationTemplate $t) => new CommunicationTemplateListResource($t)),
        );
    }

    /**
     * Créer un modèle de message.
     *
     * Permission requise : `communication_templates.create`. `subjectTemplate`
     * est exigé pour le canal e-mail uniquement.
     */
    public function store(
        StoreCommunicationTemplateRequest $request,
        ManageCommunicationTemplateAction $action,
    ): JsonResponse {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [CommunicationTemplate::class, $organizationId]);

        $template = $action->create(
            CreateCommunicationTemplateData::fromValidated($request->validated(), $organizationId),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new CommunicationTemplateDetailResource($template));
    }

    /**
     * Consulter un modèle de message.
     */
    public function show(Request $request, CommunicationTemplate $communicationTemplate): JsonResponse
    {
        $this->guardTemplate($communicationTemplate);
        $this->authorize('view', $communicationTemplate);

        return ApiResponse::ok(
            new CommunicationTemplateDetailResource($communicationTemplate->loadCount(['rules', 'communications'])),
        );
    }

    /**
     * Modifier un modèle de message.
     *
     * Permission requise : `communication_templates.update`. `code` n'est pas
     * modifiable : il identifie le modèle auprès des intégrations.
     */
    public function update(
        UpdateCommunicationTemplateRequest $request,
        CommunicationTemplate $communicationTemplate,
        ManageCommunicationTemplateAction $action,
    ): JsonResponse {
        $organizationId = $this->guardTemplate($communicationTemplate);
        $this->authorize('update', $communicationTemplate);

        $updated = $action->update(
            $communicationTemplate,
            UpdateCommunicationTemplateData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new CommunicationTemplateDetailResource($updated));
    }

    /**
     * Supprimer un modèle de message.
     *
     * Permission requise : `communication_templates.delete`. Refusé en 409 si
     * une règle ou une communication le référence.
     *
     * @response 204
     */
    public function destroy(
        Request $request,
        CommunicationTemplate $communicationTemplate,
        ManageCommunicationTemplateAction $action,
    ): JsonResponse {
        $organizationId = $this->guardTemplate($communicationTemplate);
        $this->authorize('delete', $communicationTemplate);

        try {
            $action->delete($communicationTemplate, $this->auditContext($request, $organizationId));
        } catch (CommunicationTemplateInUse $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }
}
