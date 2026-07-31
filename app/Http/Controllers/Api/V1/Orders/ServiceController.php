<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\StoreServiceRequest;
use App\Http\Requests\Api\V1\Orders\UpdateServiceRequest;
use App\Http\Resources\Api\V1\Orders\ServiceResource;
use App\Modules\Orders\Models\Service;
use App\Shared\Http\Requests\ListRequest;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catalogue des prestations : livraison, enlèvement, montage, déballage,
 * retour, reprise, stockage, deuxième passage, livraison express…
 *
 * La facturation future s'appuiera sur les services : le référentiel décrit ce
 * qui est facturable au client et payable au fournisseur, sans porter de prix.
 */
class ServiceController extends Controller
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'code' => 'code', 'name' => 'name', 'unit' => 'unit',
        'default_duration_minutes' => 'defaultDurationMinutes',
        'billable_to_customer' => 'billableToCustomer', 'payable_to_provider' => 'payableToProvider',
        'requires_address' => 'requiresAddress', 'requires_contact' => 'requiresContact', 'status' => 'status',
    ];

    /**
     * Lister les services.
     *
     * Permission requise : `services.view`. Recherche sur le code et le nom,
     * filtre `status`, tri autorisé sur `code`, `name`.
     */
    public function index(ListRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Service::class, $organizationId]);

        $query = Service::where('organization_id', $organizationId);

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->getSort('code', ['code', 'name']);
        $paginator = $query->orderBy($sort, $request->getDirection())->paginate($request->getPerPage());

        return ApiResponse::paginated($paginator->through(fn (Service $service) => new ServiceResource($service)));
    }

    /**
     * Créer un service.
     *
     * Permission requise : `services.create`. Le code est unique dans
     * l'organisation.
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Service::class, $organizationId]);

        $data = $request->validated();
        $this->assertUniqueCode($organizationId, $data['code']);

        $service = Service::create(InputMapper::map($data, self::MAPPING) + [
            'organization_id' => $organizationId,
            'status' => $data['status'] ?? 'active',
        ]);
        $this->audit($request, $organizationId, 'created', $service, null, $service->toArray());

        return ApiResponse::created(new ServiceResource($service));
    }

    /**
     * Consulter un service.
     *
     * Permission requise : `services.view`.
     */
    public function show(Request $request, string $service): JsonResponse
    {
        $model = $this->findInScope($service);
        $this->authorize('view', $model);

        return ApiResponse::ok(new ServiceResource($model));
    }

    /**
     * Modifier un service.
     *
     * Permission requise : `services.update`. Les services déjà rattachés à des
     * commandes ne sont pas réécrits : ils portent leurs propres valeurs.
     */
    public function update(UpdateServiceRequest $request, string $service): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $model = $this->findInScope($service);
        $this->authorize('update', $model);

        $data = $request->validated();
        $old = $model->toArray();

        if (isset($data['code']) && $data['code'] !== $model->code) {
            $this->assertUniqueCode($organizationId, $data['code']);
        }

        $model->update(InputMapper::map($data, self::MAPPING));
        $this->audit($request, $organizationId, 'updated', $model, $old, $model->fresh()->toArray());

        return ApiResponse::ok(new ServiceResource($model->fresh()));
    }

    /**
     * Supprimer un service.
     *
     * Permission requise : `services.delete`. Un service utilisé par une
     * commande est refusé en 409.
     *
     * @response 204
     */
    public function destroy(Request $request, string $service): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $model = $this->findInScope($service);
        $this->authorize('delete', $model);

        if ($model->orderServices()->exists()) {
            return ApiResponse::error('Impossible de supprimer un service utilisé par des commandes.', 409);
        }

        $this->audit($request, $organizationId, 'deleted', $model, $model->toArray(), null);
        $model->delete();

        return ApiResponse::noContent();
    }

    private function findInScope(string $id): Service
    {
        $organizationId = $this->requireOrganizationId();
        $service = Service::where('organization_id', $organizationId)->whereKey($id)->first();

        abort_if($service === null, 404, 'Service introuvable.');

        return $service;
    }

    private function assertUniqueCode(string $organizationId, string $code): void
    {
        validator(['code' => $code], [
            'code' => [Rule::unique('services', 'code')->where('organization_id', $organizationId)],
        ])->validate();
    }
}
