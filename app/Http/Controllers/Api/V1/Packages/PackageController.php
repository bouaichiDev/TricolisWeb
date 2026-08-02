<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Packages;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Packages\StorePackageRequest;
use App\Http\Requests\Api\V1\Packages\UpdatePackageRequest;
use App\Http\Resources\Api\V1\Packages\PackageResource;
use App\Http\Resources\Api\V1\Packages\PackageTreeResource;
use App\Modules\Orders\Actions\RecalculateOrderTotals;
use App\Modules\Orders\Models\Order;
use App\Modules\Packages\Models\Package;
use App\Modules\Packages\Services\PackageRelationResolver;
use App\Modules\Packages\Services\PackageTreeBuilder;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Colis d'une commande, éventuellement imbriqués.
 */
class PackageController extends Controller
{
    use ResolvesOrderScope;

    /** @var array<string, string> */
    private const array MAPPING = [
        'barcode' => 'barcode', 'reference' => 'reference', 'description' => 'description',
        'quantity' => 'quantity', 'weight' => 'weight', 'volume' => 'volume',
        'length' => 'length', 'width' => 'width', 'height' => 'height', 'status' => 'status',
    ];

    public function __construct(
        private readonly PackageRelationResolver $relations,
        private readonly RecalculateOrderTotals $totals,
    ) {}

    /**
     * Lister les colis d'une commande.
     *
     * Permission requise : `packages.view`. Filtre `status`, recherche sur le
     * code-barres et la référence.
     */
    public function index(ListRequest $request, Order $order): JsonResponse
    {
        $this->guardOrder($order);
        $this->authorize('managePackages', [$order, 'view']);

        $query = $order->packages()->with(['packageType', 'groupingType']);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder->where('barcode', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $paginator = $query->orderBy($request->getSort('created_at', ['created_at', 'barcode', 'reference']), $request->getDirection())
            ->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (Package $package) => new PackageResource($package)));
    }

    /**
     * Renvoyer l'arbre complet des colis d'une commande.
     *
     * Permission requise : `packages.view`. Une seule requête plate est émise,
     * l'arbre est reconstruit en mémoire.
     */
    public function tree(Request $request, Order $order, PackageTreeBuilder $builder): JsonResponse
    {
        $this->guardOrder($order);
        $this->authorize('managePackages', [$order, 'view']);

        return ApiResponse::ok(PackageTreeResource::collection($builder->build($order)));
    }

    /**
     * Créer un colis.
     *
     * Permission requise : `packages.create`. Le parent doit appartenir à la
     * même commande, la hiérarchie ne doit pas former de cycle, et le
     * code-barres est unique dans toute la plateforme.
     */
    public function store(StorePackageRequest $request, Order $order): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('managePackages', [$order, 'create']);
        $this->assertOrderIsEditable($order);

        $data = $request->validated();
        $attributes = InputMapper::map($data, self::MAPPING);
        $this->assertUniqueBarcode($data['barcode'] ?? null);

        $package = $order->packages()->make($attributes);
        $package->order_id = $order->id;
        $this->relations->apply($package, $data, $order, $organizationId);

        $package->save();
        $this->totals->execute($order);
        $this->audit($request, $organizationId, 'created', $package, null, $package->toArray());

        return ApiResponse::created(new PackageResource($package));
    }

    /**
     * Consulter un colis.
     *
     * Permission requise : `packages.view`.
     */
    public function show(Request $request, Order $order, Package $package): JsonResponse
    {
        $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $package, 'Colis');
        $this->authorize('managePackages', [$order, 'view']);

        return ApiResponse::ok(new PackageResource($package->load(['packageType', 'groupingType', 'packageOrderLines'])));
    }

    /**
     * Modifier un colis.
     *
     * Permission requise : `packages.update`. Déplacer un colis dans la
     * hiérarchie repasse par les mêmes contrôles qu'à la création.
     */
    public function update(UpdatePackageRequest $request, Order $order, Package $package): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $package, 'Colis');
        $this->authorize('managePackages', [$order, 'update']);
        $this->assertOrderIsEditable($order);

        $data = $request->validated();
        $old = $package->toArray();

        if (array_key_exists('barcode', $data) && $data['barcode'] !== $package->barcode) {
            $this->assertUniqueBarcode($data['barcode']);
        }

        $package->fill(InputMapper::map($data, self::MAPPING));
        $this->relations->apply($package, $data, $order, $organizationId);
        $package->save();

        $this->totals->execute($order);
        $this->audit($request, $organizationId, 'updated', $package, $old, $package->fresh()->toArray());

        return ApiResponse::ok(new PackageResource($package->fresh()));
    }

    /**
     * Supprimer un colis.
     *
     * Permission requise : `packages.delete`. Un colis contenant des enfants est
     * refusé en 409 : les détacher d'abord évite une suppression en cascade
     * silencieuse.
     *
     * @response 204
     */
    public function destroy(Request $request, Order $order, Package $package): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $package, 'Colis');
        $this->authorize('managePackages', [$order, 'delete']);
        $this->assertOrderIsEditable($order);

        if ($package->children()->exists()) {
            return ApiResponse::error('Impossible de supprimer un colis contenant d’autres colis.', 409);
        }

        $this->audit($request, $organizationId, 'deleted', $package, $package->toArray(), null);
        $package->delete();
        $this->totals->execute($order);

        return ApiResponse::noContent();
    }

    private function assertUniqueBarcode(?string $barcode): void
    {
        if ($barcode === null) {
            return;
        }

        validator(['barcode' => $barcode], ['barcode' => [Rule::unique('packages', 'barcode')]])->validate();
    }
}
