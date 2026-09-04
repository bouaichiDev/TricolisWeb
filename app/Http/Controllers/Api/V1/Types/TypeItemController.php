<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Types;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Types\ListTypeItemRequest;
use App\Http\Requests\Api\V1\Types\StoreTypeItemRequest;
use App\Http\Requests\Api\V1\Types\UpdateTypeItemRequest;
use App\Http\Resources\Api\V1\Types\TypeItemResource;
use App\Modules\Types\Models\Type;
use App\Modules\Types\Models\TypeItem;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Les valeurs d'un référentiel : « Camion 19T », « Palette », « Rolls ».
 *
 * Remplace `VehicleTypeController`, `PackageTypeController` et
 * `GroupingTypeController`, qui faisaient la même chose sur trois tables de
 * même forme.
 */
class TypeItemController extends Controller
{
    /** Colonne de base de données → champ d'API. */
    private const array MAPPING = [
        'code' => 'code',
        'name' => 'name',
        'status' => 'status',
        'position' => 'position',
    ];

    /**
     * Lister les valeurs, filtrées par source.
     *
     * Permission requise : `types.view`. `type` prend le code de la source —
     * `vehicle`, `package`, `grouping` — et `typeId` son identifiant. Sans
     * filtre, toutes les valeurs de l'organisation sont rendues.
     */
    public function index(ListTypeItemRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [TypeItem::class, $organizationId]);

        $query = TypeItem::inOrganization($organizationId)->with('type');

        if ($request->filled('type')) {
            $code = $request->validated('type');
            $query->whereHas('type', fn ($builder) => $builder->where('code', $code));
        }

        if ($request->filled('typeId')) {
            $query->where('type_id', $request->validated('typeId'));
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->getSort('position', ['position', 'code', 'name', 'created_at']);
        $paginator = $query->orderBy($sort, $request->getDirection())
            ->orderBy('code')
            ->paginate($request->getPerPage());

        return ApiResponse::paginated(
            $paginator->through(fn (TypeItem $item) => new TypeItemResource($item)),
        );
    }

    /**
     * Ajouter une valeur.
     *
     * Permission requise : `types.create`. Le code est unique au sein de sa
     * source : « STD » peut désigner un colis standard et un véhicule standard.
     */
    public function store(StoreTypeItemRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [TypeItem::class, $organizationId]);

        $data = $request->validated();
        $this->assertUniqueCode($organizationId, $data['typeId'], $data['code']);

        $item = TypeItem::create(
            InputMapper::map($data, self::MAPPING) + [
                'organization_id' => $organizationId,
                'type_id' => $data['typeId'],
            ],
        );

        $this->audit($request, $organizationId, 'created', $item, null, $item->toArray());

        return ApiResponse::created(new TypeItemResource($item->load('type')));
    }

    /** Consulter une valeur. Permission requise : `types.view`. */
    public function show(TypeItem $typeItem): JsonResponse
    {
        $this->authorize('view', $typeItem);

        return ApiResponse::ok(new TypeItemResource($typeItem->load('type')));
    }

    /**
     * Modifier une valeur.
     *
     * Permission requise : `types.update`. Les colis et véhicules déjà créés
     * conservent leur valeur : seule l'étiquette change.
     */
    public function update(UpdateTypeItemRequest $request, TypeItem $typeItem): JsonResponse
    {
        $this->authorize('update', $typeItem);

        $data = $request->validated();
        $old = $typeItem->toArray();

        if (isset($data['code']) && $data['code'] !== $typeItem->code) {
            $this->assertUniqueCode($typeItem->organization_id, $typeItem->type_id, $data['code']);
        }

        $typeItem->update(InputMapper::map($data, self::MAPPING));
        $this->audit(
            $request,
            $typeItem->organization_id,
            'updated',
            $typeItem,
            $old,
            $typeItem->fresh()->toArray(),
        );

        return ApiResponse::ok(new TypeItemResource($typeItem->fresh()->load('type')));
    }

    /**
     * Supprimer une valeur.
     *
     * Permission requise : `types.delete`. Une valeur encore portée par un
     * colis ou un véhicule est refusée en 409 : la supprimer laisserait une
     * référence pendante.
     *
     * @response 204
     */
    public function destroy(Request $request, TypeItem $typeItem): JsonResponse
    {
        $this->authorize('delete', $typeItem);

        if ($typeItem->isInUse()) {
            return ApiResponse::error('Impossible de supprimer une valeur encore utilisée.', 409);
        }

        $this->audit($request, $typeItem->organization_id, 'deleted', $typeItem, $typeItem->toArray(), null);
        $typeItem->delete();

        return ApiResponse::noContent();
    }

    private function assertUniqueCode(string $organizationId, string $typeId, string $code): void
    {
        validator(['code' => $code], [
            'code' => [
                Rule::unique('type_items', 'code')
                    ->where('organization_id', $organizationId)
                    ->where('type_id', $typeId),
            ],
        ])->validate();
    }
}
