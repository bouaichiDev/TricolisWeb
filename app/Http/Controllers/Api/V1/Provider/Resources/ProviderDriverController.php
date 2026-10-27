<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Resources;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\Resources\ListProviderResourcesRequest;
use App\Http\Requests\Api\V1\Provider\Resources\StoreProviderDriverRequest;
use App\Http\Requests\Api\V1\Provider\Resources\UpdateProviderDriverRequest;
use App\Http\Resources\Api\V1\Provider\ProviderDriverResource;
use App\Modules\Drivers\Actions\CreateDriverAction;
use App\Modules\Drivers\DTOs\CreateDriverData;
use App\Modules\ProviderPortal\Actions\UpdateProviderDriver;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderResourceScope;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\AuditContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chauffeurs du fournisseur courant. Le fournisseur et l'organisation viennent
 * du contexte ; les écritures passent par les Actions métier existantes, qui
 * auditent. Un chauffeur d'ailleurs répond 404 (`ProviderResourceScope`).
 */
class ProviderDriverController extends Controller
{
    public function __construct(
        private readonly CurrentProviderContext $context,
        private readonly ProviderResourceScope $scope,
    ) {}

    public function index(ListProviderResourcesRequest $request): JsonResponse
    {
        $search = $request->validated('search');
        $like = '%'.addcslashes((string) $search, '%_\\').'%';

        $drivers = $this->scope->drivers()
            ->with('user')
            ->when($request->validated('status'), static fn ($query, string $status) => $query->where('status', $status))
            ->when($search, static fn ($query) => $query->where(static fn ($inner) => $inner
                ->where('code', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhereHas('user', static fn ($user) => $user->where('email', 'like', $like))))
            ->orderBy('code')
            ->paginate($request->perPage());

        return ApiResponse::paginated($drivers->through(static fn ($driver) => (new ProviderDriverResource($driver))->resolve()));
    }

    public function show(string $driver): JsonResponse
    {
        return ApiResponse::ok(new ProviderDriverResource($this->scope->driver($driver)->load('user')));
    }

    public function store(StoreProviderDriverRequest $request, CreateDriverAction $create): JsonResponse
    {
        $data = CreateDriverData::fromValidated([
            ...$request->validated(),
            'providerId' => $this->context->providerId(),
            'status' => 'active',
        ]);
        $driver = $create->execute($data, $this->portalAudit($request));

        return ApiResponse::created(new ProviderDriverResource($driver->load('user')));
    }

    public function update(UpdateProviderDriverRequest $request, string $driver, UpdateProviderDriver $update): JsonResponse
    {
        $updated = $update->execute($driver, $request->validated(), $this->portalAudit($request));

        return ApiResponse::ok(new ProviderDriverResource($updated->load('user')));
    }

    private function portalAudit(Request $request): AuditContext
    {
        return new AuditContext($this->context->organizationId(), $this->context->user(), $request->ip());
    }
}
