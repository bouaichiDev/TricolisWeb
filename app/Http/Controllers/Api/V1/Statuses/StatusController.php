<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Statuses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Statuses\ListStatusRequest;
use App\Http\Requests\Api\V1\Statuses\StoreStatusRequest;
use App\Http\Requests\Api\V1\Statuses\UpdateStatusRequest;
use App\Http\Resources\Api\V1\Statuses\StatusResource;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Services\StatusSources;
use App\Modules\Statuses\Services\StatusUsage;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel des statuts, commun à toute la plateforme.
 *
 * Il donne à un code brut — « draft » — son libellé, son icône et son rang.
 * Tout membre le lit ; seule la plateforme l'écrit, parce qu'un statut décrit
 * le cycle de vie du domaine et non la préférence d'un organisme.
 */
class StatusController extends Controller
{
    /**
     * Colonne de base de données → champ d'API, dans cet ordre.
     *
     * @var array<string, string>
     */
    private const array MAPPING = [
        'source' => 'source',
        'status' => 'status',
        'code' => 'code',
        'label' => 'label',
        'icon' => 'icon',
        'active' => 'active',
        'is_to_send' => 'isToSend',
        'position' => 'position',
    ];

    public function __construct(private readonly StatusUsage $usage) {}

    /**
     * Lister les statuts.
     *
     * Permission requise : `statuses.view`. Filtres `source` et `active`,
     * recherche sur le code et le libellé, tri sur `source`, `status`, `code`.
     */
    public function index(ListStatusRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Status::class);

        $query = Status::query();

        if ($request->filled('source')) {
            $query->where('source', $request->validated('source'));
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$search}%")
                ->orWhere('label', 'like', "%{$search}%"));
        }

        $sort = $request->getSort('source', ['source', 'status', 'code', 'label', 'position']);
        $paginator = $query
            ->orderBy($sort, $request->getDirection())
            ->orderBy('position')
            ->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (Status $status) => new StatusResource($status)));
    }

    /**
     * Lister les entités auxquelles un statut peut se rapporter.
     *
     * Permission requise : `statuses.view`. La liste est dérivée de la morph
     * map, filtrée sur les tables qui portent réellement une colonne `status`.
     */
    public function sources(): JsonResponse
    {
        $this->authorize('viewAny', Status::class);

        return ApiResponse::ok(StatusSources::all());
    }

    /**
     * Créer un statut.
     *
     * Permission requise : `statuses.create`, réservée à la plateforme.
     */
    public function store(StoreStatusRequest $request): JsonResponse
    {
        $this->authorize('create', Status::class);

        $status = Status::create(InputMapper::map($request->validated(), self::MAPPING));
        $this->auditStatus($request, 'created', $status, null, $status->toArray());

        return ApiResponse::created(new StatusResource($status));
    }

    /**
     * Consulter un statut.
     *
     * Permission requise : `statuses.view`.
     */
    public function show(Status $status): JsonResponse
    {
        $this->authorize('view', $status);

        return ApiResponse::ok(new StatusResource($status));
    }

    /**
     * Modifier un statut.
     *
     * Permission requise : `statuses.update`, réservée à la plateforme.
     * `source` n'est pas modifiable : les enregistrements qui portent déjà le
     * code suivraient sinon en silence vers un autre domaine.
     */
    public function update(UpdateStatusRequest $request, Status $status): JsonResponse
    {
        $this->authorize('update', $status);

        $old = $status->toArray();
        $status->update(InputMapper::map($request->validated(), self::MAPPING));
        $this->auditStatus($request, 'updated', $status, $old, $status->fresh()?->toArray());

        return ApiResponse::ok(new StatusResource($status->fresh()));
    }

    /**
     * Supprimer un statut.
     *
     * Permission requise : `statuses.delete`, réservée à la plateforme. Un
     * statut encore porté par des enregistrements n'est pas supprimé : les
     * lignes concernées afficheraient un code sans libellé. Le refus dit
     * combien d'enregistrements le retiennent.
     */
    public function destroy(Request $request, Status $status): JsonResponse
    {
        $this->authorize('delete', $status);

        $this->usage->assertUnused($status);

        $snapshot = $status->toArray();
        $status->delete();
        $this->auditStatus($request, 'deleted', $status, $snapshot, null);

        return ApiResponse::noContent();
    }

    /**
     * Audit d'un statut.
     *
     * Le journal est indexé par organisation ; ce référentiel n'en a pas. Les
     * écritures sont donc rattachées à l'organisation active de l'auteur, qui
     * est celle depuis laquelle l'administrateur plateforme travaille.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function auditStatus(Request $request, string $action, Status $status, ?array $oldValues, ?array $newValues): void
    {
        $organizationId = $this->organizationId();

        if ($organizationId === null) {
            return;
        }

        $this->audit($request, $organizationId, $action, $status, $oldValues, $newValues);
    }
}
