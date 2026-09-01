<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Templates;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Templates\ListTemplateRequest;
use App\Http\Requests\Api\V1\Templates\StoreTemplateRequest;
use App\Http\Requests\Api\V1\Templates\UpdateTemplateRequest;
use App\Http\Resources\Api\V1\Templates\TemplateDetailResource;
use App\Http\Resources\Api\V1\Templates\TemplateListResource;
use App\Modules\Templates\Actions\ManageTemplateAction;
use App\Modules\Templates\DTOs\CreateTemplateData;
use App\Modules\Templates\DTOs\UpdateTemplateData;
use App\Modules\Templates\Exceptions\TemplateInUse;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Queries\TemplateListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Modèles — messages et documents.
 *
 * Une seule table, un seul écran, une seule API : le §0.1 interdit d'ouvrir un
 * second CRUD pour les modèles de facture. Le menu peut en offrir deux accès,
 * ils mènent ici.
 */
class TemplateController extends Controller
{
    use BuildsAuditContext;
    use ResolvesTemplateScope;

    /**
     * Lister les modèles.
     *
     * Permission requise : `templates.view`. La recherche porte sur le code, le
     * nom, l'objet et le corps. `customerId=global` ne retient que les modèles
     * du transporteur.
     */
    public function index(ListTemplateRequest $request, TemplateListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Template::class, $organizationId]);

        return ApiResponse::paginated(
            $query->paginate($request, $organizationId)
                ->through(fn (Template $template) => new TemplateListResource($template)),
        );
    }

    /**
     * Créer un modèle.
     *
     * Permission requise : `templates.create`. `subjectTemplate` est exigé pour
     * le canal e-mail uniquement ; un modèle de facture n'a ni canal ni objet.
     */
    public function store(StoreTemplateRequest $request, ManageTemplateAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Template::class, $organizationId]);

        $template = $action->create(
            CreateTemplateData::fromValidated($request->validated(), $organizationId),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new TemplateDetailResource($template));
    }

    /**
     * Consulter un modèle.
     */
    public function show(Request $request, Template $template): JsonResponse
    {
        $this->guardTemplate($template);
        $this->authorize('view', $template);

        return ApiResponse::ok(new TemplateDetailResource(
            $template->load(['customer:id,code,name', 'service:id,code,name'])
                ->loadCount(['rules', 'communications', 'invoices']),
        ));
    }

    /**
     * Modifier un modèle.
     *
     * Permission requise : `templates.update`. `code` n'est pas modifiable : il
     * identifie le modèle auprès des intégrations.
     */
    public function update(
        UpdateTemplateRequest $request,
        Template $template,
        ManageTemplateAction $action,
    ): JsonResponse {
        $organizationId = $this->guardTemplate($template);
        $this->authorize('update', $template);

        $updated = $action->update(
            $template,
            UpdateTemplateData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new TemplateDetailResource($updated));
    }

    /**
     * Supprimer un modèle.
     *
     * Permission requise : `templates.delete`. Refusé en 409 si une règle, une
     * communication ou une facture le référence.
     *
     * @response 204
     */
    public function destroy(Request $request, Template $template, ManageTemplateAction $action): JsonResponse
    {
        $organizationId = $this->guardTemplate($template);
        $this->authorize('delete', $template);

        try {
            $action->delete($template, $this->auditContext($request, $organizationId));
        } catch (TemplateInUse $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }
}
