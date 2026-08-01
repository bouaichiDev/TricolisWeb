<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Packages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Packages\StoreReferentialRequest;
use App\Http\Requests\Api\V1\Packages\UpdateReferentialRequest;
use App\Http\Resources\Api\V1\Packages\ReferentialResource;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Socle commun aux deux référentiels du module Colis.
 *
 * `PackageType` et `GroupingType` ont exactement la même forme au diagramme —
 * code, nom, statut, portée organisationnelle. Les dupliquer produirait deux
 * contrôleurs identiques à maintenir en parallèle.
 */
abstract class PackageReferentialController extends Controller
{
    /** @var array<string, string> */
    protected const array MAPPING = ['code' => 'code', 'name' => 'name', 'status' => 'status'];

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    abstract protected function tableName(): string;

    abstract protected function entityLabel(): string;

    /**
     * Lister les entrées du référentiel.
     *
     * Permission requise : `packages.view`. Recherche sur le code et le nom,
     * filtre `status`, tri autorisé sur `code`, `name`, `created_at`.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [$this->modelClass(), $organizationId]);

        $query = $this->modelClass()::where('organization_id', $organizationId);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->getSort('code', ['code', 'name', 'created_at']);
        $paginator = $query->orderBy($sort, $request->getDirection())->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (Model $type) => new ReferentialResource($type)));
    }

    /**
     * Créer une entrée du référentiel.
     *
     * Permission requise : `packages.create`. Le code est unique dans
     * l'organisation active.
     */
    public function store(StoreReferentialRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [$this->modelClass(), $organizationId]);

        $data = $request->validated();
        $this->assertUniqueCode($organizationId, $data['code']);

        $type = $this->modelClass()::create(InputMapper::map($data, self::MAPPING) + ['organization_id' => $organizationId]);
        $this->audit($request, $organizationId, 'created', $type, null, $type->toArray());

        return ApiResponse::created(new ReferentialResource($type));
    }

    /**
     * Modifier une entrée du référentiel.
     *
     * Permission requise : `packages.update`. Les colis déjà créés conservent
     * leur type : seule l'étiquette change.
     */
    public function update(UpdateReferentialRequest $request, string $id): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $type = $this->findInScope($id, $organizationId);
        $this->authorize('update', $type);

        $data = $request->validated();
        $old = $type->toArray();

        if (isset($data['code']) && $data['code'] !== $type->code) {
            $this->assertUniqueCode($organizationId, $data['code']);
        }

        $type->update(InputMapper::map($data, self::MAPPING));
        $this->audit($request, $organizationId, 'updated', $type, $old, $type->fresh()->toArray());

        return ApiResponse::ok(new ReferentialResource($type->fresh()));
    }

    /**
     * Supprimer une entrée du référentiel.
     *
     * Permission requise : `packages.delete`. Une entrée utilisée par des colis
     * est refusée en 409.
     *
     * @response 204
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $type = $this->findInScope($id, $organizationId);
        $this->authorize('delete', $type);

        if ($type->packages()->exists()) {
            return ApiResponse::error(sprintf('Impossible de supprimer un %s utilisé par des colis.', $this->entityLabel()), 409);
        }

        $this->audit($request, $organizationId, 'deleted', $type, $type->toArray(), null);
        $type->delete();

        return ApiResponse::noContent();
    }

    protected function findInScope(string $id, string $organizationId): Model
    {
        $type = $this->modelClass()::where('organization_id', $organizationId)->whereKey($id)->first();

        abort_if($type === null, 404, sprintf('%s introuvable.', ucfirst($this->entityLabel())));

        return $type;
    }

    protected function assertUniqueCode(string $organizationId, string $code): void
    {
        validator(['code' => $code], [
            'code' => [Rule::unique($this->tableName(), 'code')->where('organization_id', $organizationId)],
        ])->validate();
    }
}
