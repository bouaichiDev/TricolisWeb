<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Planning\ListPlanningPoolRequest;
use App\Http\Resources\Api\V1\Planning\PlanningPoolResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Planning\Services\LoadingServices;
use App\Modules\Planning\Services\PlanningEligibility;
use App\Modules\Tours\Models\Tour;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Ce qui attend d'être planifié.
 *
 * Le pool n'est **pas une table** : c'est une lecture des commandes dont au
 * moins un service attend une tournée. En créer une donnerait une seconde
 * vérité à tenir à jour à chaque planification.
 *
 * L'éligibilité est celle qu'applique la planification elle-même — même
 * service, mêmes règles. Deux définitions divergeraient au premier changement,
 * et l'écran proposerait des services que le serveur refuse.
 */
class PlanningPoolController extends Controller
{
    /**
     * Lister les commandes ayant des services à planifier.
     *
     * Permission requise : `tours.view` — c'est un écran de planification, pas
     * un annuaire de commandes.
     *
     * Filtres : `requestedDate`, `customerId`, `agencyId`, `search`. Le tri est
     * fixe : la date demandée d'abord, ce qui presse en tête.
     */
    public function index(ListPlanningPoolRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Tour::class, $organizationId]);

        $query = Order::where('organization_id', $organizationId)
            ->whereHas('orderServices', function ($services) use ($request): void {
                $services->whereIn('status', PlanningEligibility::PLANNABLE_STATUSES)
                    ->whereNotNull('address_id')
                    ->whereDoesntHave('tourStopServices', fn ($assignments) => $assignments
                        ->where('is_active_assignment', true));

                if ($request->filled('requestedDate')) {
                    $services->whereDate('requested_date', $request->validated('requestedDate'));
                }
            })
            ->with(['customer:id,code,name', 'orderServices' => function ($services): void {
                $services->whereIn('status', PlanningEligibility::PLANNABLE_STATUSES)
                    ->whereNotNull('address_id')
                    ->whereDoesntHave('tourStopServices', fn ($assignments) => $assignments
                        ->where('is_active_assignment', true))
                    ->with(['service:id,code,name', 'address'])
                    ->orderBy('sequence');
            }]);

        if ($request->filled('customerId')) {
            $query->where('customer_id', $request->validated('customerId'));
        }

        if ($request->filled('agencyId')) {
            $query->where('agency_id', $request->validated('agencyId'));
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');
            $query->where(fn ($builder) => $builder
                ->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%")));
        }

        // `orders` ne porte pas de date : elle vit sur chaque service. Le tri
        // se fait donc sur le numero, et la ressource rend la date la plus
        // proche pour que l'ecran sache ce qui presse.
        $paginator = $query->orderBy('order_number')->paginate($request->getPerPage());

        // Une seule fois pour toute la page : la reconnaissance passe par les
        // codes regles de l'organisation, pas par une constante ecrite ici.
        $loadingIds = app(LoadingServices::class)->serviceIds(
            Organization::findOrFail($organizationId),
        );

        return ApiResponse::paginated(
            $paginator->through(fn (Order $order) => new PlanningPoolResource($order, $loadingIds)),
        );
    }
}
