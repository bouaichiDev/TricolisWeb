<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\CustomerPortal\Queries\CustomerCalendarQuery;
use App\Modules\CustomerPortal\Services\Tracking\ServicePlanning;
use App\Modules\CustomerPortal\Services\Tracking\ServiceSlot;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\Config;

/**
 * Les commandes du client, en événements FullCalendar (§7).
 *
 * Un événement par commande, placé sur la première prestation qui tombe dans
 * la période ; chaque prestation garde sa propre date dans `services`. Les
 * actions viennent de `CustomerOrderCapabilities` — le calendrier n'en déduit
 * aucune. Aucune couleur : le référentiel des statuts n'en définit pas, et le
 * portail n'en invente pas.
 *
 * Trois requêtes quelle que soit la période : les commandes, leurs arrêts,
 * leurs capacités.
 */
final readonly class CustomerCalendarEvents
{
    public function __construct(
        private CustomerCalendarQuery $query,
        private ServicePlanning $planning,
        private CustomerOrderCapabilities $capabilities,
        private CustomerStatusLabels $labels,
    ) {}

    /**
     * @param  array{from: string, to: string, status?: list<string>, service?: list<string>, search?: string|null}  $filters
     * @return array{events: list<array<string, mixed>>, truncated: bool}
     */
    public function for(Customer $customer, array $filters): array
    {
        $max = Config::integer('customer_portal.tracking.calendar_max_events', 500);
        $orders = $this->query->orders($customer, $filters, $max + 1);
        $truncated = $orders->count() > $max;
        $orders = $orders->take($max);

        $stops = $this->planning->stopsFor($orders->flatMap->orderServices, $customer->organization_id);
        $actions = $this->capabilities->forOrders($orders);
        $events = [];

        foreach ($orders as $order) {
            $slots = $order->orderServices
                ->map(static fn (OrderService $service): array => ['service' => $service, 'slot' => ServiceSlot::of($service, $stops[$service->id] ?? null)])
                ->filter(static fn (array $entry): bool => $entry['slot'] !== null && self::within($entry['slot']['start'], $filters))
                ->sortBy(static fn (array $entry): string => $entry['slot']['start'])
                ->values();

            if ($slots->isNotEmpty()) {
                $events[] = $this->event($order, $slots->all(), $actions[$order->id]);
            }
        }

        return ['events' => $events, 'truncated' => $truncated];
    }

    /**
     * @param  list<array{service: OrderService, slot: array{start: string, end: string|null, allDay: bool, source: string}}>  $slots
     * @param  array<string, bool>  $actions
     * @return array<string, mixed>
     */
    private function event(Order $order, array $slots, array $actions): array
    {
        $first = $slots[0]['slot'];

        return [
            'id' => $order->id,
            'title' => $order->order_number,
            'start' => $first['start'],
            'end' => $first['end'],
            'allDay' => $first['allDay'],
            'extendedProps' => [
                'reference' => $order->order_number,
                'customerReference' => $order->customer_reference,
                'status' => $this->labels->present(MorphMap::ORDER, $order->status),
                'serviceCount' => $order->orderServices->count(),
                'slotSource' => $first['source'],
                'services' => array_map(fn (array $entry): array => [
                    'id' => $entry['service']->id,
                    'name' => $entry['service']->service?->name,
                    'status' => $this->labels->present(MorphMap::ORDER_SERVICE, $entry['service']->status),
                    ...$entry['slot'],
                ], $slots),
                'actions' => $actions,
            ],
        ];
    }

    /**
     * La date de début tombe-t-elle dans `[from, to[` ? Même convention que
     * FullCalendar : la borne de fin est exclue.
     *
     * @param  array<string, mixed>  $filters
     */
    private static function within(string $start, array $filters): bool
    {
        $day = substr($start, 0, 10);

        return $day >= $filters['from'] && $day < $filters['to'];
    }
}
