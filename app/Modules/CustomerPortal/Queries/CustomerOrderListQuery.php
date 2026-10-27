<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Queries;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * La liste des commandes d'un client, et d'aucun autre.
 *
 * Le filtre `customer_id` est posé en premier et n'est jamais optionnel : il
 * vient du Customer courant, pas de la requête. Les autres filtres portent
 * sur des colonnes réelles — `order_number`, `customer_reference`,
 * `order_date`, `status`, le service des prestations.
 *
 * La liste ne charge pas le détail : seulement le nombre de services et le
 * premier jour demandé, calculés en sous-requêtes.
 */
final class CustomerOrderListQuery
{
    /** @var array<string, string> tri exposé => colonne */
    public const array SORTS = [
        'orderDate' => 'order_date',
        'reference' => 'order_number',
        'status' => 'status',
        'createdAt' => 'created_at',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(Customer $customer, array $filters): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->withCount('orderServices')
            ->withMin('orderServices as first_requested_date', 'requested_date');

        $this->filter($query, $filters);

        $column = self::SORTS[$filters['sort'] ?? 'orderDate'] ?? 'order_date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($filters['perPage'] ?? 25));
    }

    /**
     * @param  Builder<Order>  $query
     * @param  array<string, mixed>  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(static fn (Builder $builder) => $builder
                ->where('order_number', 'like', $search)
                ->orWhere('customer_reference', 'like', $search)
                ->orWhere('external_reference', 'like', $search));
        }

        if (! empty($filters['reference'])) {
            $query->where('order_number', 'like', '%'.$filters['reference'].'%');
        }

        if (! empty($filters['customerReference'])) {
            $query->where('customer_reference', 'like', '%'.$filters['customerReference'].'%');
        }

        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        if (! empty($filters['dateFrom'])) {
            $query->whereDate('order_date', '>=', $filters['dateFrom']);
        }

        if (! empty($filters['dateTo'])) {
            $query->whereDate('order_date', '<=', $filters['dateTo']);
        }

        if (! empty($filters['service'])) {
            $query->whereHas('orderServices', static fn (Builder $builder) => $builder->where('service_id', $filters['service']));
        }
    }
}
