<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Types;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Types\StoreTypeRequest;
use App\Http\Requests\Api\V1\Types\UpdateTypeRequest;
use App\Http\Resources\Api\V1\Types\TypeResource;
use App\Modules\Types\Models\Type;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Les sources de référentiel d'une organisation.
 *
 * Véhicule, colis et groupage sont livrées ; l'organisme en ajoute d'autres
 * sans qu'une table, un modèle ni une route ne soient écrits. C'est le but de
 * la fusion : trois tables jumelles demandaient trois fois le même code.
 */
class TypeController extends Controller
{
    /** Colonne de base de données → champ d'API. */
    private const array MAPPING = ['code' => 'code', 'name' => 'name', 'status' => 'status'];

    /**
     * Lister les sources de l'organisation.
     *
     * Permission requise : `types.view`.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Type::class, $organizationId]);

        $query = Type::inOrganization($organizationId)->withCount('items');

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->getSort('name', ['code', 'name', 'created_at']);
        $paginator = $query->orderBy($sort, $request->getDirection())->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (Type $type) => new TypeResource($type)));
    }

    /**
     * Déclarer une source.
     *
     * Permission requise : `types.create`. Le code est unique dans
     * l'organisation active.
     */
    public function store(StoreTypeRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Type::class, $organizationId]);

        $data = $request->validated();
        $this->assertUniqueCode($organizationId, $data['code']);

        $type = Type::create(
            InputMapper::map($data, self::MAPPING) + ['organization_id' => $organizationId],
        );

        $this->audit($request, $organizationId, 'created', $type, null, $type->toArray());

        return ApiResponse::created(new TypeResource($type));
    }

    /** Consulter une source. Permission requise : `types.view`. */
    public function show(Type $type): JsonResponse
    {
        $this->authorize('view', $type);

        return ApiResponse::ok(new TypeResource($type->loadCount('items')));
    }

    /**
     * Modifier une source.
     *
     * Permission requise : `types.update`. Le code d'une source structurelle
     * est figé : le schéma s'y réfère par lui.
     */
    public function update(UpdateTypeRequest $request, Type $type): JsonResponse
    {
        $this->authorize('update', $type);

        $data = $request->validated();
        $old = $type->toArray();

        if ($type->is_system) {
            unset($data['code']);
        }

        if (isset($data['code']) && $data['code'] !== $type->code) {
            $this->assertUniqueCode($type->organization_id, $data['code']);
        }

        $type->update(InputMapper::map($data, self::MAPPING));
        $this->audit($request, $type->organization_id, 'updated', $type, $old, $type->fresh()->toArray());

        return ApiResponse::ok(new TypeResource($type->fresh()->loadCount('items')));
    }

    /**
     * Supprimer une source.
     *
     * Permission requise : `types.delete`. Une source structurelle est refusée
     * par la politique ; une source qui porte encore des valeurs l'est ici, en
     * 409 — les supprimer en cascade effacerait sans le dire des valeurs
     * peut-être utilisées.
     *
     * @response 204
     */
    public function destroy(Request $request, Type $type): JsonResponse
    {
        $this->authorize('delete', $type);

        if ($type->items()->exists()) {
            return ApiResponse::error('Impossible de supprimer une source qui porte encore des valeurs.', 409);
        }

        $this->audit($request, $type->organization_id, 'deleted', $type, $type->toArray(), null);
        $type->delete();

        return ApiResponse::noContent();
    }

    private function assertUniqueCode(string $organizationId, string $code): void
    {
        validator(['code' => $code], [
            'code' => [Rule::unique('types', 'code')->where('organization_id', $organizationId)],
        ])->validate();
    }
}
