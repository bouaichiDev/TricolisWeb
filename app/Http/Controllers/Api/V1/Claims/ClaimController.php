<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Claims;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTourScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Claims\ListClaimRequest;
use App\Http\Requests\Api\V1\Claims\StoreClaimRequest;
use App\Http\Requests\Api\V1\Claims\UpdateClaimRequest;
use App\Http\Resources\Api\V1\Claims\ClaimDetailResource;
use App\Http\Resources\Api\V1\Claims\ClaimListResource;
use App\Modules\Claims\Actions\CreateClaimAction;
use App\Modules\Claims\Actions\DeleteClaimAction;
use App\Modules\Claims\Actions\UpdateClaimAction;
use App\Modules\Claims\DTOs\CreateClaimData;
use App\Modules\Claims\DTOs\UpdateClaimData;
use App\Modules\Claims\Exceptions\ClaimNotDeletable;
use App\Modules\Claims\Models\Claim;
use App\Modules\Claims\Queries\ClaimListQuery;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Tours\Models\Tour;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Réclamations clients.
 */
class ClaimController extends Controller
{
    use BuildsAuditContext;
    use ResolvesTourScope;

    /**
     * Lister les réclamations de l'organisation active.
     *
     * Permission requise : `claims.view`. Recherche sur `title`, `description`,
     * `cause`, `decision`, `follow_up` et `result`.
     */
    public function index(ListClaimRequest $request, ClaimListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Claim::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId));
    }

    /**
     * Créer une réclamation.
     *
     * Permission requise : `claims.create`. Les champs de résolution ne sont pas
     * acceptés ici : une réclamation naît ouverte.
     */
    public function store(StoreClaimRequest $request, CreateClaimAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Claim::class, $organizationId]);

        $claim = $action->execute(
            CreateClaimData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
            now()->toDateTimeString(),
        );

        return ApiResponse::created(new ClaimDetailResource($claim));
    }

    /**
     * Consulter une réclamation.
     *
     * Permission requise : `claims.view`.
     */
    public function show(Request $request, Claim $claim): JsonResponse
    {
        $this->guardClaim($claim);
        $this->authorize('view', $claim);

        return ApiResponse::ok(new ClaimDetailResource(
            $claim->load(['customer', 'order', 'tour', 'creator', 'responsibleUser']),
        ));
    }

    /**
     * Modifier une réclamation, ou la clôturer.
     *
     * Permission requise : `claims.update`. Renseigner `closedAt` clôture le
     * dossier et produit une entrée d'audit dédiée.
     */
    public function update(UpdateClaimRequest $request, Claim $claim, UpdateClaimAction $action): JsonResponse
    {
        $organizationId = $this->guardClaim($claim);
        $this->authorize('update', $claim);

        $updated = $action->execute(
            $claim,
            UpdateClaimData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::ok(new ClaimDetailResource($updated));
    }

    /**
     * Supprimer une réclamation ouverte.
     *
     * Permission requise : `claims.delete`. Refusée en 409 si la réclamation
     * est clôturée.
     *
     * @response 204
     */
    public function destroy(Request $request, Claim $claim, DeleteClaimAction $action): JsonResponse
    {
        $organizationId = $this->guardClaim($claim);
        $this->authorize('delete', $claim);

        try {
            $action->execute($claim, $this->auditContext($request, $organizationId));
        } catch (ClaimNotDeletable $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::noContent();
    }

    /**
     * Réclamations d'un client.
     */
    public function byCustomer(ListClaimRequest $request, Customer $customer, ClaimListQuery $query): JsonResponse
    {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('viewAny', [Claim::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['customer_id' => $customer->id]));
    }

    /**
     * Créer une réclamation pour le client de l'URL.
     */
    public function storeForCustomer(StoreClaimRequest $request, Customer $customer, CreateClaimAction $action): JsonResponse
    {
        $organizationId = $this->guardCustomer($customer);
        $this->authorize('create', [Claim::class, $organizationId]);

        $claim = $action->execute(
            CreateClaimData::fromValidated(['customerId' => $customer->id] + $request->validated()),
            $this->auditContext($request, $organizationId),
            now()->toDateTimeString(),
        );

        return ApiResponse::created(new ClaimDetailResource($claim));
    }

    /**
     * Réclamations liées à une commande.
     */
    public function byOrder(ListClaimRequest $request, Order $order, ClaimListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($order->organization_id === $organizationId, 404, 'Commande introuvable.');
        $this->authorize('viewAny', [Claim::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['order_id' => $order->id]));
    }

    /**
     * Réclamations liées à une tournée.
     */
    public function byTour(ListClaimRequest $request, Tour $tour, ClaimListQuery $query): JsonResponse
    {
        $organizationId = $this->guardTour($tour);
        $this->authorize('viewAny', [Claim::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['tour_id' => $tour->id]));
    }

    private function respond(mixed $paginator): JsonResponse
    {
        return ApiResponse::paginated($paginator->through(fn (Claim $c) => new ClaimListResource($c)));
    }

    private function guardClaim(Claim $claim): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($claim->organization_id === $organizationId, 404, 'Réclamation introuvable.');

        return $organizationId;
    }

    private function guardCustomer(Customer $customer): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($customer->organization_id === $organizationId, 404, 'Client introuvable.');

        return $organizationId;
    }
}
