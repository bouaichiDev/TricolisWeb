<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Queries;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Tours\Models\TourStopService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Les commandes d'un client qui ont quelque chose à montrer dans une période.
 *
 * Une commande entre dans la période si l'une de ses prestations y est
 * **demandée**, ou si l'arrêt qui l'exécute y est **planifié** — à l'heure
 * d'arrivée, ou au jour de la tournée quand l'heure manque. La date retenue
 * pour l'affichage est ensuite tranchée par `ServiceSlot`.
 *
 * Le filtre `customer_id` est posé en premier et vient du contexte, jamais de
 * la requête. La période est obligatoire et bornée : le calendrier ne charge
 * que ce qu'il montre.
 */
final class CustomerCalendarQuery
{
    /**
     * @param  array{from: string, to: string, status?: list<string>, service?: list<string>, search?: string|null}  $filters
     * @return Collection<int, Order>
     */
    public function orders(Customer $customer, array $filters, int $limit): Collection
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->startOfDay();

        $query = Order::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->where(fn (Builder $inPeriod) => $inPeriod
                ->whereHas('orderServices', static fn (Builder $service) => $service
                    ->where('requested_date', '>=', $from->toDateString())
                    ->where('requested_date', '<', $to->toDateString()))
                ->orWhereHas('orderServices.tourStopServices', fn (Builder $assignment) => $this->plannedIn($assignment, $from, $to)))
            ->with(['orderServices' => static fn ($services) => $services->orderBy('sequence'), 'orderServices.service:id,code,name']);

        $this->filter($query, $filters);

        return $query->orderBy('order_date')->orderBy('id')->limit($limit)->get();
    }

    /**
     * @param  Builder<TourStopService>  $assignment
     */
    private function plannedIn(Builder $assignment, Carbon $from, Carbon $to): void
    {
        $assignment->where('is_active_assignment', true)
            ->whereHas('tourStop', static fn (Builder $stop) => $stop
                ->where(static fn (Builder $timed) => $timed
                    ->where('planned_arrival_at', '>=', $from)
                    ->where('planned_arrival_at', '<', $to))
                ->orWhere(static fn (Builder $untimed) => $untimed
                    ->whereNull('planned_arrival_at')
                    ->whereHas('tour', static fn (Builder $tour) => $tour
                        ->where('tour_date', '>=', $from->toDateString())
                        ->where('tour_date', '<', $to->toDateString()))));
    }

    /**
     * @param  Builder<Order>  $query
     * @param  array<string, mixed>  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        if (! empty($filters['service'])) {
            $query->whereHas('orderServices', static fn (Builder $builder) => $builder->whereIn('service_id', (array) $filters['service']));
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(static fn (Builder $builder) => $builder
                ->where('order_number', 'like', $search)
                ->orWhere('customer_reference', 'like', $search)
                ->orWhere('external_reference', 'like', $search));
        }
    }
}
