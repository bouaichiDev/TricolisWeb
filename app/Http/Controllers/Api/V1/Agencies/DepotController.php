<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agencies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agencies\StoreDepotRequest;
use App\Http\Requests\Api\V1\Agencies\UpdateDepotRequest;
use App\Http\Resources\Api\V1\Agencies\DepotResource;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dépôts d'une agence (`Agency 1 — 0..* Depot`). Les dépôts sont toujours
 * adressés sous leur agence : `/api/v1/agencies/{agency}/depots`.
 */
class DepotController extends Controller
{
    /**
     * Lister les dépôts d'une agence.
     *
     * Permission requise : `depots.view`. Recherche sur `name` et `code`,
     * filtre `status`, tri autorisé sur `name`, `code`, `created_at`.
     */
    public function index(ListRequest $request, Agency $agency): JsonResponse
    {
        $this->authorize('view', $agency);
        $this->authorize('viewAny', [Depot::class, $agency->organization_id]);

        $query = Depot::where('agency_id', $agency->id);

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

        return ApiResponse::paginated($paginator->through(fn (Depot $depot): DepotResource => new DepotResource($depot)));
    }

    /**
     * Créer un dépôt dans une agence.
     *
     * Permission requise : `depots.create`. L'agence doit appartenir à
     * l'organisation active.
     */
    public function store(StoreDepotRequest $request, Agency $agency): JsonResponse
    {
        $this->authorize('view', $agency);
        $this->authorize('create', [Depot::class, $agency->organization_id]);

        $validated = $request->validated();

        $depot = Depot::create([
            'agency_id' => $agency->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'active',
        ]);
        $this->audit($request, $agency->organization_id, 'created', $depot, null, $depot->toArray());

        return ApiResponse::created(new DepotResource($depot));
    }

    /**
     * Consulter un dépôt.
     *
     * Permission requise : `depots.view`.
     */
    public function show(Request $request, Agency $agency, Depot $depot): JsonResponse
    {
        $this->authorize('view', $agency);
        $this->authorize('view', $depot);

        return ApiResponse::ok(new DepotResource($depot));
    }

    /**
     * Modifier un dépôt.
     *
     * Permission requise : `depots.update`.
     */
    public function update(UpdateDepotRequest $request, Agency $agency, Depot $depot): JsonResponse
    {
        $this->authorize('view', $agency);
        $this->authorize('update', $depot);
        $oldValues = $depot->toArray();

        $validated = $request->validated();

        $data = [];

        foreach ([
            'code' => 'code',
            'name' => 'name',
            'status' => 'status',
        ] as $db => $input) {
            if (array_key_exists($input, $validated)) {
                $data[$db] = $validated[$input];
            }
        }

        $depot->update($data);
        $this->audit($request, $agency->organization_id, 'updated', $depot, $oldValues, $depot->fresh()->toArray());

        return ApiResponse::ok(new DepotResource($depot->fresh()));
    }

    /**
     * Supprimer un dépôt.
     *
     * Permission requise : `depots.delete`.
     *
     * @response 204
     */
    public function destroy(Request $request, Agency $agency, Depot $depot): JsonResponse
    {
        $this->authorize('view', $agency);
        $this->authorize('delete', $depot);

        $this->audit($request, $agency->organization_id, 'deleted', $depot, $depot->toArray(), null);
        $depot->delete();

        return ApiResponse::noContent();
    }
}
