<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agencies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agencies\StoreAgencyRequest;
use App\Http\Requests\Api\V1\Agencies\UpdateAgencyRequest;
use App\Http\Resources\Api\V1\Agencies\AgencyResource;
use App\Modules\Agencies\Models\Agency;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agences de l'organisation active (`Organization 1 — 0..* Agency`).
 */
class AgencyController extends Controller
{
    /**
     * Lister les agences.
     *
     * Permission requise : `agencies.view`. Recherche sur `name` et `code`,
     * filtre `status`, tri autorisé sur `name`, `code`, `created_at`.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();

        $this->authorize('viewAny', [Agency::class, $organizationId]);

        $query = Agency::where('organization_id', $organizationId);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->getSort('name', ['name', 'code', 'created_at']);
        $direction = $request->getDirection();

        $paginator = $query->orderBy($sort, $direction)->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (Agency $agency): AgencyResource => new AgencyResource($agency)));
    }

    /**
     * Créer une agence.
     *
     * Permission requise : `agencies.create`. L'agence est rattachée à
     * l'organisation de l'en-tête `X-Organization-Id`.
     */
    public function store(StoreAgencyRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();

        $this->authorize('create', [Agency::class, $organizationId]);

        $validated = $request->validated();

        $agency = Agency::create([
            'organization_id' => $organizationId,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'short_name' => $validated['shortName'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'color' => $validated['color'] ?? null,
            'loading_point' => $validated['loadingPoint'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);
        $this->audit($request, $organizationId, 'created', $agency, null, $agency->toArray());

        return ApiResponse::created(new AgencyResource($agency));
    }

    /**
     * Consulter une agence.
     *
     * Permission requise : `agencies.view`.
     */
    public function show(Request $request, Agency $agency): JsonResponse
    {
        $this->authorize('view', $agency);

        return ApiResponse::ok(new AgencyResource($agency));
    }

    /**
     * Modifier une agence.
     *
     * Permission requise : `agencies.update`.
     */
    public function update(UpdateAgencyRequest $request, Agency $agency): JsonResponse
    {
        $this->authorize('update', $agency);
        $oldValues = $agency->toArray();

        $validated = $request->validated();

        $data = [];

        foreach ([
            'code' => 'code',
            'name' => 'name',
            'short_name' => 'shortName',
            'email' => 'email',
            'phone' => 'phone',
            'color' => 'color',
            'loading_point' => 'loadingPoint',
            'status' => 'status',
        ] as $db => $input) {
            if (array_key_exists($input, $validated)) {
                $data[$db] = $validated[$input];
            }
        }

        $agency->update($data);
        $this->audit($request, $agency->organization_id, 'updated', $agency, $oldValues, $agency->fresh()->toArray());

        return ApiResponse::ok(new AgencyResource($agency->fresh()));
    }

    /**
     * Supprimer une agence.
     *
     * Permission requise : `agencies.delete`. Une agence possédant encore des
     * dépôts est refusée en 409.
     *
     * @response 204
     */
    public function destroy(Request $request, Agency $agency): JsonResponse
    {
        $this->authorize('delete', $agency);

        if ($agency->depots()->exists()) {
            return ApiResponse::error('Impossible de supprimer une agence possédant des dépôts.', 409);
        }
        $this->audit($request, $agency->organization_id, 'deleted', $agency, $agency->toArray(), null);
        $agency->delete();

        return ApiResponse::noContent();
    }
}
