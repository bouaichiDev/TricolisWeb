<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Billing\ListBillableServiceRequest;
use App\Http\Requests\Api\V1\Billing\SuggestBillableServiceRequest;
use App\Http\Resources\Api\V1\Billing\BillableServiceResource;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Queries\BillableServiceQuery;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Les prestations encore facturables à un client.
 *
 * Un sélecteur, pas un CRUD : ces services existent déjà, on ne fait que dire
 * lesquels peuvent entrer dans une facture. Le §42 interdit de laisser React en
 * décider seul — deux règles finiraient par diverger.
 *
 * Permission requise : `invoices.view`. C'est une lecture préparatoire à une
 * facture, pas une lecture de commandes.
 */
class BillableServiceController extends Controller
{
    public function index(ListBillableServiceRequest $request, BillableServiceQuery $query, Customer $customer): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($customer->organization_id === $organizationId, 404, 'Client introuvable.');
        $this->authorize('viewAny', [Invoice::class, $organizationId]);

        $paginator = $query->paginate($request, $customer->id, $organizationId);

        return ApiResponse::paginated($paginator->through(
            fn (OrderService $service) => new BillableServiceResource($service),
        ));
    }

    /**
     * Compléter un filtre par les valeurs qui existent.
     *
     * Permission requise : `invoices.view`. Les suggestions se cherchent dans
     * **tout** l'éligible, pas dans la page affichée : c'est leur seule raison
     * d'être, un numéro absent des vingt-cinq lignes visibles pouvant très bien
     * exister trois pages plus loin.
     */
    public function suggestions(SuggestBillableServiceRequest $request, BillableServiceQuery $query, Customer $customer): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($customer->organization_id === $organizationId, 404, 'Client introuvable.');
        $this->authorize('viewAny', [Invoice::class, $organizationId]);

        return ApiResponse::ok($query->suggest(
            (string) $request->validated('field'),
            $request->validated('term'),
            $customer->id,
            $organizationId,
        ));
    }
}
