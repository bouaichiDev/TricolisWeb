<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Exports;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Integrations\ResolvesCustomerScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Exports\RetryExportJobRequest;
use App\Http\Requests\Api\V1\Exports\StoreExportJobRequest;
use App\Http\Requests\Api\V1\Integrations\ListConfigurationRequest;
use App\Http\Resources\Api\V1\Exports\ExportJobResource;
use App\Modules\Exports\Actions\CreateExportJobAction;
use App\Modules\Exports\Actions\RetryExportJobAction;
use App\Modules\Exports\DTOs\CreateExportJobData;
use App\Modules\Exports\Exceptions\ExportJobNotRetryable;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Integrations\Queries\IntegrationListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exécutions d'export.
 *
 * Ni `update`, ni `destroy` : le §27 les interdit. `retry` est la seule
 * écriture, et n'ajoute aucune colonne.
 */
class ExportJobController extends Controller
{
    use BuildsAuditContext;
    use ResolvesCustomerScope;

    /**
     * Lister les exports.
     *
     * Permission requise : `export_jobs.view`. Ordre par défaut :
     * `generated_at` décroissant.
     */
    public function index(ListConfigurationRequest $request, IntegrationListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [ExportJob::class, $organizationId]);

        $paginator = $query->paginate('job', $request, $organizationId);

        return ApiResponse::paginated($paginator->through(fn (ExportJob $j) => new ExportJobResource($j)));
    }

    /**
     * Demander un export.
     *
     * Permission requise : `export_jobs.create`. Le client est déduit de la
     * configuration, qui doit être active.
     *
     * Le fichier n'est **pas** généré : voir `phase-8-analysis.md` §11 — aucune
     * règle de contenu n'est définie, et le §30 interdit de produire un faux
     * export métier.
     */
    public function store(StoreExportJobRequest $request, CreateExportJobAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [ExportJob::class, $organizationId]);

        $job = $action->execute(
            CreateExportJobData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ExportJobResource($job));
    }

    /**
     * Consulter un export.
     */
    public function show(Request $request, ExportJob $exportJob): JsonResponse
    {
        $this->guardCustomerOwned($exportJob);
        $this->authorize('view', $exportJob);

        return ApiResponse::ok(new ExportJobResource($exportJob->load('configuration')));
    }

    /**
     * Relancer un export.
     *
     * Permission requise : `export_jobs.retry`. Incrémente `attemptCount` et
     * efface l'erreur. Refusé en 409 si l'export a déjà été transmis.
     */
    public function retry(RetryExportJobRequest $request, ExportJob $exportJob, RetryExportJobAction $action): JsonResponse
    {
        $organizationId = $this->guardCustomerOwned($exportJob);
        $this->authorize('retry', $exportJob);

        try {
            $retried = $action->execute(
                $exportJob,
                $request->validated('status'),
                $this->auditContext($request, $organizationId),
            );
        } catch (ExportJobNotRetryable $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::ok(new ExportJobResource($retried));
    }
}
