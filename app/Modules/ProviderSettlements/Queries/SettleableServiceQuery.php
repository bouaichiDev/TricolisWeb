<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Queries;

use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Les prestations qu'on doit encore régler à un fournisseur.
 *
 * **Le fournisseur à payer est celui de l'affectation active.** C'est le
 * problème que pose le §17 : un service peut avoir plusieurs `TourStopService`
 * historiques, chez des fournisseurs différents — une tentative échouée chez
 * l'un, la livraison réelle chez l'autre. Prendre « la dernière tournée » ferait
 * payer celui qui n'a rien livré.
 *
 * Le modèle donnait la réponse sans qu'on ait à l'inventer : la Phase 5 garantit
 * **une seule affectation active** par service, transactionnellement. Les
 * affectations désactivées racontent où le service est passé ; l'active dit qui
 * l'a exécuté.
 *
 * Une tournée sans fournisseur — le transporteur roule lui-même — ne rend donc
 * aucun service réglable : il n'y a personne à payer.
 */
final readonly class SettleableServiceQuery
{
    public function paginate(ListRequest $request, string $providerId, string $organizationId): LengthAwarePaginator
    {
        $query = OrderService::query()
            ->where('status', OrderServiceStatus::COMPLETED->value)
            ->whereDoesntHave('settlementLine')
            ->whereHas('order', fn ($order) => $order->where('organization_id', $organizationId))
            ->whereHas('tourStopServices', fn ($assignments) => $assignments
                ->where('is_active_assignment', true)
                ->whereHas('tourStop', fn ($stop) => $stop
                    ->whereHas('tour', fn ($tour) => $tour->where('provider_id', $providerId))))
            ->with([
                'order:id,order_number,customer_reference,customer_id',
                'order.customer:id,code,name',
                'service:id,code,name',
                'address',
            ]);

        if ($request->filled('periodFrom')) {
            $query->whereDate('requested_date', '>=', $request->validated('periodFrom'));
        }

        if ($request->filled('periodTo')) {
            $query->whereDate('requested_date', '<=', $request->validated('periodTo'));
        }

        if ($request->filled('search')) {
            $search = $request->validated('search');

            $query->where(fn ($builder) => $builder
                ->where('service_number', 'like', "%{$search}%")
                ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$search}%")));
        }

        return $query
            ->orderBy('requested_date')
            ->orderBy('service_number')
            ->paginate($request->getPerPage());
    }
}
