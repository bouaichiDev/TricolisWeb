<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Communications\ListCommunicationRequest;
use App\Http\Requests\Api\V1\Communications\StoreCommunicationRuleRequest;
use App\Http\Requests\Api\V1\Communications\UpdateCommunicationRuleRequest;
use App\Http\Resources\Api\V1\Communications\CommunicationRuleDetailResource;
use App\Http\Resources\Api\V1\Communications\CommunicationRuleListResource;
use App\Modules\Communications\Actions\ManageCommunicationRuleAction;
use App\Modules\Communications\DTOs\CreateCommunicationRuleData;
use App\Modules\Communications\DTOs\UpdateCommunicationRuleData;
use App\Modules\Communications\Exceptions\CommunicationRuleInUse;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Queries\CommunicationListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Règles de communication.
 */
class CommunicationRuleController extends Controller
{
    use BuildsAuditContext;
    use ResolvesCommunicationScope;

    /**
     * Lister les règles de communication.
     *
     * Permission requise : `communication_rules.view`.
     */
    public function index(ListCommunicationRequest $request, CommunicationListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [CommunicationRule::class, $organizationId]);

        return ApiResponse::paginated(
            $query->paginate('rule', $request, $organizationId)
                ->through(fn (CommunicationRule $r) => new CommunicationRuleListResource($r)),
        );
    }

    /**
     * Créer une règle de communication.
     *
     * Permission requise : `communication_rules.create`. Le modèle doit relever
     * de l'organisation active, et son service — s'il en a un — doit concorder
     * avec celui de la règle.
     */
    public function store(StoreCommunicationRuleRequest $request, ManageCommunicationRuleAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [CommunicationRule::class, $organizationId]);

        $rule = $action->create(
            CreateCommunicationRuleData::fromValidated($request->validated(), $organizationId),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new CommunicationRuleDetailResource($rule->load('template')));
    }

    /**
     * Consulter une règle de communication.
     */
    public function show(Request $request, CommunicationRule $communicationRule): JsonResponse
    {
        $this->guardRule($communicationRule);
        $this->authorize('view', $communicationRule);

        return ApiResponse::ok(new CommunicationRuleDetailResource(
            $communicationRule->load('template')->loadCount('communications'),
        ));
    }

    /**
     * Modifier une règle de communication.
     *
     * Permission requise : `communication_rules.update`.
     */
    public function update(
        UpdateCommunicationRuleRequest $request,
        CommunicationRule $communicationRule,
        ManageCommunicationRuleAction $action,
    ): JsonResponse {
        $organizationId = $this->guardRule($communicationRule);
        $this->authorize('update', $communicationRule);

        $updated = $action->update(
            $communicationRule,
            UpdateCommunicationRuleData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new CommunicationRuleDetailResource($updated->load('template')));
    }

    /**
     * Supprimer une règle de communication.
     *
     * Permission requise : `communication_rules.delete`. Refusé en 409 si des
     * communications en découlent.
     *
     * @response 204
     */
    public function destroy(
        Request $request,
        CommunicationRule $communicationRule,
        ManageCommunicationRuleAction $action,
    ): JsonResponse {
        $organizationId = $this->guardRule($communicationRule);
        $this->authorize('delete', $communicationRule);

        try {
            $action->delete($communicationRule, $this->auditContext($request, $organizationId));
        } catch (CommunicationRuleInUse $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }
}
