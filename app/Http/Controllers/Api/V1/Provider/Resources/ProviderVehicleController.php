<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider\Resources;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\Resources\ListProviderResourcesRequest;
use App\Http\Requests\Api\V1\Provider\Resources\StoreProviderVehicleRequest;
use App\Http\Requests\Api\V1\Provider\Resources\UpdateProviderVehicleRequest;
use App\Http\Resources\Api\V1\Provider\ProviderVehicleResource;
use App\Modules\Fleet\Actions\CreateVehicleAction;
use App\Modules\Fleet\Actions\UpdateVehicleAction;
use App\Modules\Fleet\DTOs\CreateVehicleData;
use App\Modules\Fleet\DTOs\UpdateVehicleData;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderResourceScope;
use App\Modules\Types\Models\TypeItem;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\AuditContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Véhicules du fournisseur courant, et les types de véhicule de son
 * organisation. Les écritures passent par les Actions métier existantes ; un
 * véhicule d'ailleurs répond 404 (`ProviderResourceScope`).
 */
class ProviderVehicleController extends Controller
{
    public function __construct(
        private readonly CurrentProviderContext $context,
        private readonly ProviderResourceScope $scope,
    ) {}

    public function index(ListProviderResourcesRequest $request): JsonResponse
    {
        $search = $request->validated('search');
        $like = '%'.addcslashes((string) $search, '%_\\').'%';

        $vehicles = $this->scope->vehicles()
            ->with('vehicleType')
            ->when($request->validated('status'), static fn ($query, string $status) => $query->where('status', $status))
            ->when($search, static fn ($query) => $query->where(static fn ($inner) => $inner
                ->where('code', 'like', $like)
                ->orWhere('registration_number', 'like', $like)))
            ->orderBy('code')
            ->paginate($request->perPage());

        return ApiResponse::paginated($vehicles->through(static fn ($vehicle) => (new ProviderVehicleResource($vehicle))->resolve()));
    }

    public function show(string $vehicle): JsonResponse
    {
        return ApiResponse::ok(new ProviderVehicleResource($this->scope->vehicle($vehicle)->load('vehicleType')));
    }

    public function store(StoreProviderVehicleRequest $request, CreateVehicleAction $create): JsonResponse
    {
        $data = CreateVehicleData::fromValidated([
            ...$request->validated(),
            'providerId' => $this->context->providerId(),
            'status' => 'active',
        ]);

        return ApiResponse::created(new ProviderVehicleResource($create->execute($data, $this->portalAudit($request))->load('vehicleType')));
    }

    public function update(UpdateProviderVehicleRequest $request, string $vehicle, UpdateVehicleAction $update): JsonResponse
    {
        $updated = DB::transaction(fn () => $update->execute(
            $this->scope->vehicle($vehicle, lock: true),
            UpdateVehicleData::fromValidated($request->validated()),
            $this->portalAudit($request),
        ));

        return ApiResponse::ok(new ProviderVehicleResource($updated->load('vehicleType')));
    }

    /**
     * Les types de véhicule actifs de l'organisation, pour le formulaire.
     */
    public function types(): JsonResponse
    {
        $types = TypeItem::where('organization_id', $this->context->organizationId())
            ->whereHas('type', static fn ($query) => $query->where('code', 'vehicle'))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return ApiResponse::ok($types->map(static fn (TypeItem $type): array => ['id' => $type->id, 'code' => $type->code, 'name' => $type->name])->values());
    }

    private function portalAudit(Request $request): AuditContext
    {
        return new AuditContext($this->context->organizationId(), $this->context->user(), $request->ip());
    }
}
