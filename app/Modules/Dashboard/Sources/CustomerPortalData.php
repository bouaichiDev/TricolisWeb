<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\CustomerPortal\Services\CustomerStatusLabels;
use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Données des widgets du portail client.
 *
 * **Toute** requête part de `orders()`, qui pose le client et l'organisation.
 * Sans client dans le contexte, la source refuse de calculer : un widget du
 * portail servi sans ce filtre montrerait les commandes de tous les clients
 * du transporteur.
 */
final readonly class CustomerPortalData implements DashboardDataSource
{
    public function __construct(private CustomerStatusLabels $labels) {}

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function resolve(array $keys, DashboardContext $context): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = match ($key) {
                'customer_orders_count' => DashboardPayload::kpi(
                    $context->restrict($this->orders($context), 'order_date')->count()
                ),
                'customer_deliveries_upcoming' => DashboardPayload::kpi(
                    $this->orders($context)
                        ->where('status', '!=', OrderStatus::CANCELLED->value)
                        ->whereHas('orderServices', static fn (Builder $query) => $query->whereBetween('requested_date', [
                            $context->today->toDateString(),
                            $context->today->addDays(6)->toDateString(),
                        ]))
                        ->count()
                ),
                'customer_orders_by_status' => DashboardPayload::donut($this->byStatus($context), MorphMap::ORDER),
                'customer_recent_orders' => DashboardPayload::list($this->recent($context)),
                default => null,
            };
        }

        return $data;
    }

    /**
     * @return Builder<Order>
     */
    private function orders(DashboardContext $context): Builder
    {
        if ($context->customerId === null) {
            throw new LogicException('Un widget du portail client exige un client dans le contexte.');
        }

        return Order::query()
            ->where('organization_id', $context->organizationId)
            ->where('customer_id', $context->customerId);
    }

    /**
     * Répartition par statut, chaque part accompagnée de son libellé : le
     * portail n'a pas le référentiel des statuts pour le retrouver.
     *
     * @return array<int, array{code: string, value: int, label: string|null}>
     */
    private function byStatus(DashboardContext $context): array
    {
        return $context->restrict($this->orders($context), 'order_date')
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (object $row): array => [
                'code' => (string) $row->status,
                'value' => (int) $row->total,
                'label' => $this->labels->present(MorphMap::ORDER, (string) $row->status)['label'],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recent(DashboardContext $context): array
    {
        return $context->restrict($this->orders($context), 'order_date')
            ->orderByDesc('order_date')
            ->limit(6)
            ->get(['id', 'order_number', 'customer_reference', 'order_date', 'status'])
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'title' => $order->order_number,
                'subtitle' => $order->customer_reference,
                'status' => $order->status?->value,
                'statusLabel' => $this->labels->present(MorphMap::ORDER, $order->status)['label'],
                'statusSource' => MorphMap::ORDER,
                'date' => $order->order_date?->toIso8601String(),
                'route' => "/orders/{$order->id}",
            ])
            ->all();
    }
}
