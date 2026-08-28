<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pricing;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pricing\ListPriceListRequest;
use App\Http\Requests\Api\V1\Pricing\StorePriceListRequest;
use App\Http\Requests\Api\V1\Pricing\UpdatePriceListRequest;
use App\Http\Resources\Api\V1\Pricing\PriceListResource;
use App\Modules\Pricing\Models\PriceList;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Les barèmes de l'organisation active.
 *
 * Un barème appartient au transporteur, jamais au client : un client n'y a
 * accès que par le rattachement, et le §169BJ interdit qu'une organisation
 * voie la liste d'une autre — d'où le 404 plutôt qu'un 403, qui en révélerait
 * l'existence.
 */
class PriceListController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les barèmes.
     *
     * Permission requise : `price_lists.view`. Le filtre `scope` sépare les
     * barèmes généraux des barèmes négociés, qui ne se consultent pas au même
     * moment.
     */
    public function index(ListPriceListRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [PriceList::class, $organizationId]);

        $paginator = PriceList::query()
            ->where('organization_id', $organizationId)
            ->when($request->filled('scope'), fn ($query) => $query->where('scope', $request->validated('scope')))
            ->when($request->filled('customerId'), fn ($query) => $query->whereHas(
                'customers',
                fn ($customers) => $customers->whereKey($request->validated('customerId')),
            ))
            ->when($request->filled('search'), fn ($query) => $query->where(
                fn ($builder) => $builder
                    ->where('code', 'like', '%'.$request->validated('search').'%')
                    ->orWhere('name', 'like', '%'.$request->validated('search').'%'),
            ))
            ->withCount(['rules', 'matrices'])
            ->with('customers:id,code,name')
            ->orderBy('scope')
            ->orderBy('code')
            ->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(
            fn (PriceList $list) => new PriceListResource($list),
        ));
    }

    /**
     * Consulter un barème, avec ses règles et ses matrices.
     */
    public function show(PriceList $priceList): JsonResponse
    {
        $this->guard($priceList);
        $this->authorize('view', $priceList);

        return ApiResponse::ok(new PriceListResource($priceList->load([
            'customers:id,code,name',
            'rules' => fn ($rules) => $rules->with(['service:id,code,name', 'conditions'])
                ->withCount('matrixRows')
                ->orderBy('priority')
                ->orderBy('code'),
            'matrices.service:id,code,name',
            'matrices.rows.priceRule:id,code,formula',
        ])));
    }

    /**
     * Créer un barème.
     *
     * Permission requise : `price_lists.create`.
     */
    public function store(StorePriceListRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [PriceList::class, $organizationId]);

        $priceList = DB::transaction(function () use ($request, $organizationId): PriceList {
            $list = PriceList::create([
                'organization_id' => $organizationId,
                'code' => $request->validated('code'),
                'name' => $request->validated('name'),
                'scope' => $request->validated('scope'),
                'valid_from' => $request->validated('validFrom'),
                'valid_to' => $request->validated('validTo'),
                'is_active' => $request->boolean('isActive', true),
            ]);

            $list->customers()->sync($request->validated('customerIds') ?? []);

            return $list;
        });

        return ApiResponse::created(new PriceListResource($priceList->load('customers:id,code,name')));
    }

    /**
     * Modifier un barème.
     *
     * Permission requise : `price_lists.update`. La portée n'est pas
     * modifiable : basculer une liste client en globale l'appliquerait d'un
     * coup à toute la clientèle.
     */
    public function update(UpdatePriceListRequest $request, PriceList $priceList): JsonResponse
    {
        $this->guard($priceList);
        $this->authorize('update', $priceList);

        DB::transaction(function () use ($request, $priceList): void {
            $priceList->update(array_filter([
                'code' => $request->validated('code'),
                'name' => $request->validated('name'),
                'valid_from' => $request->validated('validFrom'),
                'valid_to' => $request->validated('validTo'),
            ], static fn ($value): bool => $value !== null) + ($request->has('isActive')
                ? ['is_active' => $request->boolean('isActive')]
                : []));

            if ($request->has('customerIds')) {
                $priceList->customers()->sync($request->validated('customerIds') ?? []);
            }
        });

        return ApiResponse::ok(new PriceListResource($priceList->fresh()->load('customers:id,code,name')));
    }

    /**
     * Supprimer un barème.
     *
     * Permission requise : `price_lists.delete`. Les règles et matrices
     * partent avec lui ; l'historique des calculs survit, ses références
     * passant à nul — un prix déjà facturé doit rester explicable.
     */
    public function destroy(PriceList $priceList): JsonResponse
    {
        $this->guard($priceList);
        $this->authorize('delete', $priceList);

        $priceList->delete();

        return ApiResponse::noContent();
    }

    /** Hors périmètre : 404, jamais 403, qui révélerait l'existence. */
    private function guard(PriceList $priceList): void
    {
        abort_unless(
            $priceList->organization_id === $this->requireOrganizationId(),
            404,
            'Tarif introuvable.',
        );
    }
}
