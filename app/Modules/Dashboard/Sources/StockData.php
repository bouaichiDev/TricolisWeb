<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Models\StockReservation;

/**
 * Stock.
 *
 * Aucune de ces tables ne porte d'`organization_id` : le stock appartient au
 * client, et le client à l'organisation. Chaque modèle expose déjà
 * `scopeInOrganization`, écrit pour ses écrans ; le tableau de bord s'en sert
 * plutôt que de réécrire la chaîne de jointures — deux versions du même chemin
 * finiraient par ne plus dire la même chose de l'appartenance.
 *
 * Une réservation « active » est une réservation **non libérée**. Le statut est
 * une chaîne libre, `released_at` ne l'est pas.
 */
final readonly class StockData implements DashboardDataSource
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
        $organizationId = $context->organizationId;

        return match ($key) {
            'stock_items_count' => DashboardPayload::kpi(
                StockItem::query()->inOrganization($organizationId)->count()
            ),

            // Les trois quantités sortent de `stock_balances`, la table que le
            // domaine tient à jour. Les recalculer depuis les mouvements aurait
            // donné un second total, à défendre contre le premier.
            'stock_total_quantity' => DashboardPayload::kpi($this->sum('quantity', $organizationId)),
            'stock_reserved_quantity' => DashboardPayload::kpi($this->sum('reserved_quantity', $organizationId)),
            'stock_available_quantity' => DashboardPayload::kpi($this->sum('available_quantity', $organizationId)),

            'active_stock_reservations' => DashboardPayload::kpi(
                StockReservation::query()
                    ->inOrganization($organizationId)
                    ->whereNull('released_at')
                    ->count()
            ),

            'recent_stock_movements' => DashboardPayload::list($this->recentMovements($context)),

            // La part engagee sur le total, en un seul geste : trois compteurs
            // cote a cote obligeaient a faire la division de tete.
            'stock_reserved_rate' => DashboardPayload::gauge(
                $this->sum('reserved_quantity', $organizationId),
                $this->sum('quantity', $organizationId),
            ),

            default => null,
        };
    }

    /**
     * Une somme, faite par la base.
     *
     * `SUM` sur une colonne décimale rend une chaîne : la convertir en flottant
     * ici évite que le JSON porte `"12.000"` là où le frontend attend un
     * nombre — et qu'une addition côté navigateur concatène deux quantités.
     */
    private function sum(string $column, string $organizationId): float
    {
        return (float) StockBalance::query()->inOrganization($organizationId)->sum($column);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentMovements(DashboardContext $context): array
    {
        return StockMovement::query()
            ->inOrganization($context->organizationId)
            ->with('stockItem:id,article_code,description')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'stock_item_id', 'movement_type', 'quantity', 'created_at'])
            ->map(static fn (StockMovement $movement): array => [
                'id' => $movement->getKey(),
                'title' => $movement->getAttribute('stockItem')?->article_code,
                'subtitle' => $movement->getAttribute('stockItem')?->description,
                'status' => $movement->getAttribute('movement_type'),
                'statusSource' => null,
                'date' => $movement->getAttribute('created_at')?->toIso8601String(),
                'route' => '/stock/movements/'.$movement->getKey(),
            ])
            ->all();
    }
}
