<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Integrations\CustomerImportRunResource;
use App\Modules\CustomerPortal\Services\Integrations\CustomerIntegrationScope;
use App\Modules\Integrations\Models\CustomerImportRun;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * L'historique des imports du client : dépôts, relèves automatiques, et ce
 * qui a été refusé. Lecture seule, limitée aux configurations du client.
 */
class CustomerImportRunController extends Controller
{
    public function __construct(private readonly CustomerIntegrationScope $scope) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'customerId' => ['prohibited'],
            'organizationId' => ['prohibited'],
            'status' => ['sometimes', 'nullable', Rule::in([
                CustomerImportRun::COMPLETED, CustomerImportRun::COMPLETED_WITH_ERRORS, CustomerImportRun::REJECTED, CustomerImportRun::FAILED,
            ])],
            'configurationId' => ['sometimes', 'nullable', 'string', 'ulid'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $this->scope->importRuns()
            ->with(['configuration', 'trigger'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['configurationId'] ?? null, fn ($query, $id) => $query->where('configuration_id', $id))
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['perPage'] ?? 20));

        return ApiResponse::paginated($page->through(
            static fn (CustomerImportRun $run): CustomerImportRunResource => new CustomerImportRunResource($run, forPortal: true),
        ));
    }

    public function show(string $importRun): JsonResponse
    {
        $run = $this->scope->find($this->scope->importRuns()->with(['configuration', 'trigger']), $importRun);

        return ApiResponse::ok(new CustomerImportRunResource($run, forPortal: true, withErrors: true));
    }
}
