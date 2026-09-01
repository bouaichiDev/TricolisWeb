<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\ListOrderRequest;
use App\Http\Resources\Api\V1\Orders\OrderDetailResource;
use App\Http\Resources\Api\V1\Orders\OrderListResource;
use App\Modules\Integrations\Services\CustomerApiContext;
use App\Modules\Orders\Models\Order;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Les commandes **du client qui appelle**, et d'aucun autre.
 *
 * Ce contrôleur ne réutilise pas `OrderListQuery` : cette requête-là scope par
 * **organisation** et ne traite `customerId` que comme un filtre facultatif.
 * Branchée sur une clé cliente, elle rendrait les commandes de tous les clients
 * du transporteur — le filtre se retire, une contrainte non.
 *
 * Ici l'appartenance est posée en premier, à partir du contexte authentifié, et
 * rien dans la requête ne peut l'élargir : `customerId` n'est pas lu.
 *
 * Les Policies ne s'appliquent pas — elles décrivent ce qu'un **utilisateur** de
 * l'organisme a le droit de faire, et une clé cliente n'est pas un utilisateur.
 * Le droit est vérifié en amont par le portail, sur la route.
 */
final class ClientOrderController extends Controller
{
    public function index(ListOrderRequest $request, CustomerApiContext $context): JsonResponse
    {
        $paginator = Order::query()
            ->where('customer_id', $context->customerId())
            ->where('organization_id', $context->organizationId())
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(function ($builder) use ($request): void {
                    $search = $request->validated('search');

                    foreach (['order_number', 'external_reference', 'customer_reference'] as $column) {
                        $builder->orWhere($column, 'like', "%{$search}%");
                    }
                }),
            )
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->validated('status')))
            ->withCount(['lines', 'orderServices'])
            ->orderByDesc('order_date')
            ->paginate($request->getPerPage());

        return ApiResponse::paginated(
            $paginator->through(fn (Order $order) => new OrderListResource($order)),
        );
    }

    /**
     * Une commande d'un autre client est **introuvable**, pas interdite.
     *
     * Répondre 403 confirmerait son existence, et permettrait d'énumérer les
     * commandes du transporteur en observant la différence entre les réponses.
     * C'est la même règle que le reste du projet applique entre organisations.
     */
    public function show(Order $order, CustomerApiContext $context): JsonResponse
    {
        abort_unless(
            $order->customer_id === $context->customerId()
            && $order->organization_id === $context->organizationId(),
            404,
            'Commande introuvable.',
        );

        return ApiResponse::ok(new OrderDetailResource(
            $order->load(['lines', 'packages', 'orderServices']),
        ));
    }
}
