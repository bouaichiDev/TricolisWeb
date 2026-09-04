<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;

/**
 * Commandes et services.
 *
 * Chaque chiffre sort d'un `COUNT` sur des colonnes indexées —
 * `(organization_id, status)` pour les commandes, `status` pour les services.
 * Aucun `Model::all()`, aucune collection chargée pour être comptée : sur un
 * organisme qui traite mille commandes par jour, la différence se voit à
 * l'écran.
 *
 * Les services passent par un `EXISTS` sur leur commande plutôt que par une
 * jointure. La table `order_services` ne porte pas d'`organization_id` — elle
 * le tient de sa commande — et le dupliquer pour économiser cet `EXISTS`
 * donnerait deux vérités sur la même appartenance.
 */
final readonly class OperationsData implements DashboardDataSource
{
    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function resolve(array $keys, DashboardContext $context): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->resolveOne($key, $context);
        }

        return $data;
    }

    private function resolveOne(string $key, DashboardContext $context): mixed
    {
        return match ($key) {
            'orders_today' => DashboardPayload::kpi(
                $this->orders($context)->whereBetween('order_date', $context->dayBounds())->count()
            ),

            // « À planifier » couvre deux statuts, et il le faut : une commande
            // partiellement planifiée a encore des services en attente, et la
            // ranger avec les commandes closes reviendrait à l'oublier.
            'orders_to_plan' => DashboardPayload::kpi(
                $this->orders($context)->whereIn('status', [
                    OrderStatus::READY->value,
                    OrderStatus::PARTIALLY_PLANNED->value,
                ])->count()
            ),

            'orders_in_progress' => DashboardPayload::kpi(
                $this->orders($context)->where('status', OrderStatus::IN_PROGRESS->value)->count()
            ),

            // La date de clôture n'existe pas sur une commande : `updated_at`
            // est ce qui s'en approche le plus, et le statut la qualifie. Une
            // commande achevée hier puis corrigée aujourd'hui y figurera — dire
            // « modifiée » plutôt que « close » serait exact mais illisible
            // dans une tuile.
            'orders_completed_today' => DashboardPayload::kpi(
                $this->orders($context)
                    ->where('status', OrderStatus::COMPLETED->value)
                    ->whereBetween('updated_at', $context->dayBounds())
                    ->count()
            ),

            'services_ready_to_plan' => DashboardPayload::kpi(
                $this->services($context)->where('status', OrderServiceStatus::READY_TO_PLAN->value)->count()
            ),
            'services_in_progress' => DashboardPayload::kpi(
                $this->services($context)->where('status', OrderServiceStatus::IN_PROGRESS->value)->count()
            ),
            'services_failed' => DashboardPayload::alert(
                $this->services($context)->where('status', OrderServiceStatus::FAILED->value)->count()
            ),

            'recent_orders' => DashboardPayload::list($this->recentOrders($context)),
            'orders_by_status' => DashboardPayload::chart($this->ordersByStatus($context), MorphMap::ORDER),

            default => null,
        };
    }

    /**
     * @return Builder<Order>
     */
    private function orders(DashboardContext $context): Builder
    {
        return Order::query()->where('organization_id', $context->organizationId);
    }

    /**
     * @return Builder<OrderService>
     */
    private function services(DashboardContext $context): Builder
    {
        return OrderService::query()->whereHas(
            'order',
            fn (Builder $order) => $order->where('organization_id', $context->organizationId)
        );
    }

    /**
     * Les six dernières commandes, et de quoi les reconnaître.
     *
     * Six, pas cent : cette carte donne un aperçu et un lien vers la liste, qui
     * sait filtrer, trier et paginer. Le client est chargé avec elles — sans
     * quoi six requêtes de plus suivraient, une par ligne.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentOrders(DashboardContext $context): array
    {
        return $this->orders($context)
            ->with('customer:id,name')
            ->orderByDesc('order_date')
            ->limit(6)
            ->get(['id', 'customer_id', 'order_number', 'order_date', 'status'])
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'title' => $order->order_number,
                'subtitle' => $order->customer?->name,
                'status' => $order->status?->value,
                'statusSource' => MorphMap::ORDER,
                'date' => $order->order_date?->toIso8601String(),
                'route' => "/orders/{$order->id}",
            ])
            ->all();
    }

    /**
     * Un `GROUP BY`, et non dix `COUNT` — un par statut connu.
     *
     * La requête part en `toBase()`, hors d'Eloquent : hydrater un modèle pour
     * lire un agrégat ferait passer `status` par sa conversion en énumération,
     * qui lève sur une valeur qu'elle ne connaît pas. Une commande portant un
     * statut retiré du code ferait alors échouer le tableau de bord entier,
     * pour une ligne.
     *
     * @return array<int, array{code: string, value: int}>
     */
    private function ordersByStatus(DashboardContext $context): array
    {
        $rows = $this->orders($context)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        return $rows
            ->map(static fn (object $row): array => [
                'code' => (string) $row->status,
                'value' => (int) $row->total,
            ])
            ->all();
    }
}
