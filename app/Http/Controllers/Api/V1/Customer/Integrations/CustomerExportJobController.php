<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\Integrations\CustomerExportJobResource;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\Integrations\CustomerIntegrationActivity;
use App\Modules\CustomerPortal\Services\Integrations\CustomerIntegrationScope;
use App\Modules\Exports\Actions\RetryExportJobAction;
use App\Modules\Exports\Exceptions\ExportJobNotRetryable;
use App\Modules\Exports\Models\ExportJob;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\AuditContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Les envois vers le système du client, et leur relance.
 *
 * La relance réutilise `RetryExportJobAction` — celle du back-office : même
 * verrou, même refus d'un envoi déjà transmis ou d'une configuration
 * désactivée, même audit. Le statut repart à `pending` ; le client ne le
 * choisit pas. Aucune écriture d'historique autre que celle-là.
 */
class CustomerExportJobController extends Controller
{
    public function __construct(
        private readonly CustomerIntegrationScope $scope,
        private readonly CustomerIntegrationActivity $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'customerId' => ['prohibited'],
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'processing', 'sent', 'failed'])],
            'configurationId' => ['sometimes', 'nullable', 'string', 'ulid'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $this->scope->exportJobs()
            ->with('configuration')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['configurationId'] ?? null, fn ($query, $id) => $query->where('configuration_id', $id))
            ->orderByDesc('id')
            ->paginate((int) ($filters['perPage'] ?? 20));

        $references = $this->activity->entityReferences($page->getCollection());

        return ApiResponse::paginated($page->through(
            static fn (ExportJob $job): CustomerExportJobResource => new CustomerExportJobResource($job, $references[$job->entity_id] ?? null),
        ));
    }

    public function show(string $exportJob): JsonResponse
    {
        return ApiResponse::ok($this->present($this->find($exportJob)));
    }

    public function retry(
        string $exportJob,
        Request $request,
        RetryExportJobAction $retry,
        CurrentCustomerContext $context,
        CustomerPortalAudit $audit,
    ): JsonResponse {
        $job = $this->find($exportJob);

        try {
            $retried = $retry->execute($job, 'pending', new AuditContext($context->organizationId(), $context->user(), $request->ip()));
        } catch (ExportJobNotRetryable $exception) {
            return ApiResponse::error($exception->getMessage(), JsonResponse::HTTP_CONFLICT);
        }

        $audit->record($context->customer(), $context->user(), 'integration_export_job_retried', $retried, null, [
            'exportJobId' => $retried->id,
            'configurationId' => $retried->configuration_id,
        ], $request->ip());

        return ApiResponse::ok($this->present($retried->fresh() ?? $retried));
    }

    private function find(string $id): ExportJob
    {
        /** @var ExportJob $job */
        $job = $this->scope->find($this->scope->exportJobs()->with('configuration'), $id);

        return $job;
    }

    private function present(ExportJob $job): CustomerExportJobResource
    {
        $job->loadMissing('configuration');

        return new CustomerExportJobResource($job, $this->activity->entityReferences(collect([$job]))[$job->entity_id] ?? null);
    }
}
