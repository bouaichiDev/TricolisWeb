<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Orders\Models\Order;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tracking\Services\VehiclePositionProvider;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPositionController extends Controller
{
    use ResolvesOrderScope;

    /**
     * Positions du véhicule qui exécute la commande.
     *
     * Permission requise : `orders.view`.
     *
     * **Le jeton du fournisseur ne quitte jamais le serveur.** C'est la raison
     * d'être de cette route : le navigateur la demande, elle interroge la
     * télématique avec le secret chiffré de l'organisation. Appeler le
     * fournisseur depuis le navigateur exposerait un jeton donnant accès à
     * l'historique de tous les véhicules.
     *
     * Renvoie une liste vide plutôt qu'une erreur quand rien n'est configuré,
     * qu'aucune tournée ne porte de référence, ou que le fournisseur est
     * injoignable : la commande reste consultable, seule la carte manque.
     */
    public function __invoke(Request $request, Order $order, VehiclePositionProvider $provider): JsonResponse
    {
        $this->guardOrder($order);
        $this->authorize('view', $order);

        $configuration = OrganizationApiConfiguration::query()
            ->where('organization_id', $order->organization_id)
            ->where('code', VehiclePositionProvider::CONFIGURATION_CODE)
            ->where('is_active', true)
            ->first();

        if ($configuration === null) {
            return ApiResponse::ok(['points' => [], 'reason' => 'not_configured']);
        }

        // La tournee la plus recente qui porte une reference : une commande peut
        // en traverser plusieurs, et c'est la derniere qui dit ou est le camion.
        $reference = Tour::query()
            ->whereNotNull('telematics_reference')
            ->whereIn('id', $this->tourIdsOf($order))
            ->orderByDesc('tour_date')
            ->value('telematics_reference');

        if (! is_string($reference) || $reference === '') {
            return ApiResponse::ok(['points' => [], 'reason' => 'no_reference']);
        }

        return ApiResponse::ok([
            'points' => $provider->forReference($configuration, $reference),
            'reason' => null,
        ]);
    }

    /**
     * Tournées qui desservent la commande.
     *
     * Le chemin est `order_services → tour_stop_services → tour_stops → tours` :
     * une commande ne connaît pas ses tournées directement, ce sont ses services
     * qui sont rattachés à des arrêts.
     *
     * @return array<int, string>
     */
    private function tourIdsOf(Order $order): array
    {
        return Tour::query()
            ->join('tour_stops', 'tour_stops.tour_id', '=', 'tours.id')
            ->join('tour_stop_services', 'tour_stop_services.tour_stop_id', '=', 'tour_stops.id')
            ->join('order_services', 'order_services.id', '=', 'tour_stop_services.order_service_id')
            ->where('order_services.order_id', $order->id)
            ->distinct()
            ->pluck('tours.id')
            ->all();
    }
}
