<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Queries;

use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Ce qui reste à facturer, et ce que ça coûterait.
 *
 * **La page qui sert à trouver les trous** (§169AI) : un service terminé dont
 * aucun barème ne couvre le cas se facturerait à zéro ou pas du tout, et on ne
 * s'en apercevrait qu'au moment d'éditer la facture. Ici, il se voit avant.
 *
 * Le prix affiché est **calculé sans être enregistré** : le §169AH interdit de
 * retenir un aperçu comme prix définitif, et une table d'historique remplie de
 * simulations n'expliquerait plus rien.
 */
final readonly class PrebillingQuery
{
    public function paginate(ListRequest $request, string $organizationId): LengthAwarePaginator
    {
        $query = OrderService::query()
            ->where('status', OrderServiceStatus::COMPLETED->value)
            ->whereDoesntHave('invoiceLine')
            ->whereHas('order', fn ($order) => $order->where('organization_id', $organizationId))
            ->with([
                'order:id,order_number,customer_reference,customer_id,currency_code',
                'order.customer:id,code,name',
                'service:id,code,name',
                'address',
            ]);

        if ($request->filled('customerId')) {
            $customerId = $request->validated('customerId');
            $query->whereHas('order', fn ($order) => $order->where('customer_id', $customerId));
        }

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
