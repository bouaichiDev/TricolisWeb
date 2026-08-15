<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ProofOfDelivery;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProofOfDelivery\ListProofOfDeliveryRequest;
use App\Http\Requests\Api\V1\ProofOfDelivery\StoreProofOfDeliveryRequest;
use App\Http\Resources\Api\V1\ProofOfDelivery\ProofOfDeliveryDetailResource;
use App\Http\Resources\Api\V1\ProofOfDelivery\ProofOfDeliveryListResource;
use App\Modules\Orders\Models\Order;
use App\Modules\ProofOfDelivery\Actions\CreateProofOfDeliveryAction;
use App\Modules\ProofOfDelivery\DTOs\CreateProofOfDeliveryData;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\ProofOfDelivery\Queries\ProofOfDeliveryListQuery;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Preuves de livraison.
 *
 * Ni `update`, ni `destroy` : une preuve est historique et a valeur probante.
 * Aucun endpoint d'upload non plus — signature et photo sont créées via le
 * module Documents, puis liées par identifiant.
 */
class ProofOfDeliveryController extends Controller
{
    use BuildsAuditContext;

    /**
     * Lister les preuves de livraison de l'organisation active.
     *
     * Permission requise : `proofs_of_delivery.view`. Le périmètre passe par la
     * commande, la table ne portant pas d'organisation.
     */
    public function index(ListProofOfDeliveryRequest $request, ProofOfDeliveryListQuery $query): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [ProofOfDelivery::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId));
    }

    /**
     * Créer une preuve de livraison.
     *
     * Permission requise : `proofs_of_delivery.create`. Signature et photo sont
     * facultatives et doivent désigner des documents de l'organisation de la
     * commande.
     */
    public function store(StoreProofOfDeliveryRequest $request, CreateProofOfDeliveryAction $action): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [ProofOfDelivery::class, $organizationId]);

        $proof = $action->execute(
            CreateProofOfDeliveryData::fromValidated($request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ProofOfDeliveryDetailResource($proof));
    }

    /**
     * Consulter une preuve de livraison.
     *
     * Permission requise : `proofs_of_delivery.view`.
     */
    public function show(Request $request, ProofOfDelivery $proofOfDelivery): JsonResponse
    {
        $this->guardProof($proofOfDelivery);
        $this->authorize('view', $proofOfDelivery);

        return ApiResponse::ok(new ProofOfDeliveryDetailResource(
            $proofOfDelivery->load(['signatureDocument', 'photoDocument', 'creator']),
        ));
    }

    /**
     * Preuves de livraison d'une commande.
     */
    public function byOrder(ListProofOfDeliveryRequest $request, Order $order, ProofOfDeliveryListQuery $query): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('viewAny', [ProofOfDelivery::class, $organizationId]);

        return $this->respond($query->paginate($request, $organizationId, ['order_id' => $order->id]));
    }

    /**
     * Créer une preuve de livraison rattachée à la commande de l'URL.
     *
     * Permission requise : `proofs_of_delivery.create`. `orderId` est imposé par
     * la route : le fournir différemment dans le corps n'aurait pas de sens.
     */
    public function storeForOrder(StoreProofOfDeliveryRequest $request, Order $order, CreateProofOfDeliveryAction $action): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->authorize('create', [ProofOfDelivery::class, $organizationId]);

        $proof = $action->execute(
            CreateProofOfDeliveryData::fromValidated(['orderId' => $order->id] + $request->validated()),
            $this->auditContext($request, $organizationId),
        );

        return ApiResponse::created(new ProofOfDeliveryDetailResource($proof));
    }

    private function respond(mixed $paginator): JsonResponse
    {
        return ApiResponse::paginated($paginator->through(fn (ProofOfDelivery $p) => new ProofOfDeliveryListResource($p)));
    }

    private function guardOrder(Order $order): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($order->organization_id === $organizationId, 404, 'Commande introuvable.');

        return $organizationId;
    }

    private function guardProof(ProofOfDelivery $proof): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($proof->order?->organization_id === $organizationId, 404, 'Preuve introuvable.');

        return $organizationId;
    }
}
