<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Dashboard\Services\DailySeries;
use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    /** Deux semaines pleines : assez pour voir un creux se repeter. */
    private const int COLUMN_DAYS = 14;

    /** Un mois : une tendance a besoin de plus de recul qu'un volume. */
    private const int LINE_DAYS = 30;

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
            'orders_by_status' => DashboardPayload::chart($this->groupBy($context, 'status'), MorphMap::ORDER),

            // `labels` et non `source` : la provenance vient d'une enumeration
            // PHP, que personne ne renomme. Le referentiel des statuts ne la
            // connait pas, et l'y chercher aurait rendu des codes bruts.
            'orders_by_source' => DashboardPayload::donut(
                $this->groupBy($context, 'source'),
                labels: 'orderSources',
            ),

            'orders_per_day' => $this->ordersPerDay($context),
            'orders_trend' => $this->ordersTrend($context),

            default => null,
        };
    }

    /**
     * Le volume quotidien, et sa composition.
     *
     * Quatorze jours : deux semaines pleines, de quoi voir un creux de week-end
     * se repeter sans que les colonnes deviennent des traits. Sept en auraient
     * montre un seul, trente auraient demande une colonne de six pixels.
     *
     * La repartition est celle du statut **actuel** — une commande du 12 y
     * figure avec l'etat qu'elle porte aujourd'hui, pas avec celui qu'elle avait
     * ce jour-la. Reconstituer l'histoire demanderait un journal des
     * transitions, que le domaine ne tient pas ; l'inventer donnerait un graphe
     * faux qui aurait l'air juste.
     *
     * @return array<string, mixed>
     */
    private function ordersPerDay(DashboardContext $context): array
    {
        $rows = $this->orders($context)
            ->toBase()
            ->whereBetween('order_date', $context->window(self::COLUMN_DAYS))
            ->selectRaw('DATE(order_date) as day, status as code, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(order_date)'), 'status')
            ->get();

        $built = DailySeries::build($rows, $context->windowStart(self::COLUMN_DAYS), self::COLUMN_DAYS);

        return DashboardPayload::timeseries($built['buckets'], $built['series'], MorphMap::ORDER);
    }

    /**
     * Ce qui entre, et ce qui sort.
     *
     * Deux courbes sur **un seul axe** : ce sont deux comptes de commandes, donc
     * deux grandeurs comparables. Y ajouter les services ou les tournees aurait
     * demande une seconde echelle verticale, laquelle invente une correlation
     * que les donnees ne portent pas.
     *
     * « Achevees » se lit dans `updated_at`, faute de date de cloture : une
     * commande achevee hier puis corrigee aujourd'hui compte aujourd'hui. C'est
     * la meme approximation que `orders_completed_today`, et elle est assumee —
     * une tendance la supporte mieux qu'un chiffre du jour.
     *
     * @return array<string, mixed>
     */
    private function ordersTrend(DashboardContext $context): array
    {
        $start = $context->windowStart(self::LINE_DAYS);

        $created = $this->perDay($context, 'order_date', self::LINE_DAYS);

        $completed = $this->perDay(
            $context,
            'updated_at',
            self::LINE_DAYS,
            fn (QueryBuilder $orders) => $orders->where('status', OrderStatus::COMPLETED->value),
        );

        return DashboardPayload::timeseries(
            array_map(
                static fn (int $offset): string => $start->addDays($offset)->toDateString(),
                range(0, self::LINE_DAYS - 1),
            ),
            [
                ['code' => 'created', 'values' => DailySeries::values($created, $start, self::LINE_DAYS)],
                ['code' => 'completed', 'values' => DailySeries::values($completed, $start, self::LINE_DAYS)],
            ],
            labels: 'orderTrend',
        );
    }

    /**
     * Comptes par jour sur une colonne de date, avec un filtre facultatif.
     *
     * Le nom de colonne est ecrit dans les deux seuls appels qui existent :
     * `selectRaw` ne prend pas de liaison pour un identifiant, et une colonne
     * choisie a l'exterieur serait une injection.
     *
     * @param  null|callable(QueryBuilder): mixed  $filter
     * @return Collection<int, object>
     */
    private function perDay(DashboardContext $context, string $column, int $days, ?callable $filter = null): Collection
    {
        $query = $this->orders($context)
            ->toBase()
            ->whereBetween($column, $context->window($days));

        if ($filter !== null) {
            $filter($query);
        }

        return $query
            ->selectRaw("DATE({$column}) as day, COUNT(*) as total")
            ->groupBy(DB::raw("DATE({$column})"))
            ->get();
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
     * Un `GROUP BY`, et non dix `COUNT` — un par valeur connue.
     *
     * La requête part en `toBase()`, hors d'Eloquent : hydrater un modèle pour
     * lire un agrégat ferait passer `status` par sa conversion en énumération,
     * qui lève sur une valeur qu'elle ne connaît pas. Une commande portant un
     * statut retiré du code ferait alors échouer le tableau de bord entier,
     * pour une ligne.
     *
     * Le nom de colonne ne vient jamais d'une requête : il est écrit dans les
     * deux seuls appels qui existent. Une colonne choisie à l'extérieur serait
     * une injection — `selectRaw` ne prend pas de liaison pour un identifiant.
     *
     * @return array<int, array{code: string, value: int}>
     */
    private function groupBy(DashboardContext $context, string $column): array
    {
        return $this->orders($context)
            ->toBase()
            ->selectRaw("{$column}, COUNT(*) as total")
            ->groupBy($column)
            ->orderBy($column)
            ->get()
            ->map(static fn (object $row): array => [
                'code' => (string) $row->{$column},
                'value' => (int) $row->total,
            ])
            ->all();
    }
}
