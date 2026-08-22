<?php

declare(strict_types=1);

namespace App\Modules\Stock\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockMovement;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Collection;

/**
 * Ce que la confirmation d'une commande sortirait du stock, ligne par ligne.
 *
 * Le calcul est **séparé de l'écriture** pour une raison précise : une ligne de
 * commande ne dit pas où sa marchandise se trouve. Quand un article dort dans
 * plusieurs emplacements, il faut demander lequel vider — et le demander
 * *avant* la confirmation plutôt que refuser après coup.
 *
 * Quatre situations, et une seule mène à un mouvement :
 *
 * | État | Signification |
 * | --- | --- |
 * | `resolved` | un seul emplacement, ou l'appelant l'a désigné |
 * | `ambiguous` | plusieurs emplacements en portent : il faut choisir |
 * | `insufficient` | aucun emplacement n'en a assez |
 * | `untracked` | ligne hors catalogue, ou article non suivi en stock |
 *
 * `untracked` n'est pas une erreur : une commande peut porter des lignes
 * saisies à la main, sans article de catalogue, et un article catalogué n'est
 * pas forcément entreposé chez le transporteur.
 */
final readonly class OrderStockPlanner
{
    /**
     * @param  array<string, string>  $chosenLocations  orderLineId => stockLocationId
     * @return list<array<string, mixed>>
     */
    public function plan(Order $order, array $chosenLocations = []): array
    {
        $lines = $order->lines()->get();
        $items = $this->stockItemsOf($order, $lines);
        $consumed = $this->alreadyConsumed($lines);

        return $lines
            ->map(fn (OrderLine $line): array => $this->planLine(
                $line,
                $items->get((string) $line->catalog_item_id),
                in_array($line->id, $consumed, true),
                $chosenLocations[$line->id] ?? null,
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function planLine(
        OrderLine $line,
        ?StockItem $item,
        bool $consumed,
        ?string $chosen,
    ): array {
        $base = [
            'orderLineId' => $line->id,
            'name' => $line->name,
            'articleCode' => $line->article_code,
            'quantity' => (string) $line->quantity,
            'stockItemId' => $item?->id,
            'locations' => [],
            'stockLocationId' => null,
        ];

        // Deja sorti : reconfirmer ne doit pas prelever une seconde fois.
        if ($item === null || $consumed) {
            return [...$base, 'state' => $consumed ? 'consumed' : 'untracked'];
        }

        $candidates = $this->candidates($item, (string) $line->quantity);
        $base['locations'] = $candidates->values()->all();

        if ($candidates->isEmpty()) {
            return [...$base, 'state' => 'insufficient'];
        }

        $picked = $chosen ?? ($candidates->count() === 1 ? $candidates->first()['id'] : null);

        return [
            ...$base,
            'state' => $picked === null ? 'ambiguous' : 'resolved',
            'stockLocationId' => $picked,
        ];
    }

    /**
     * Emplacements dont le disponible couvre à lui seul la quantité demandée.
     *
     * Pas de panachage entre emplacements : `StockMovement` porte **une** source,
     * et rien dans le diagramme ne décrit un prélèvement réparti. Une commande
     * qui dépasse le contenu d'un emplacement se traite donc à la main.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function candidates(StockItem $item, string $quantity): Collection
    {
        return StockBalance::query()
            ->with('stockLocation:id,location_code,zone_code')
            ->where('stock_item_id', $item->id)
            ->get()
            ->filter(fn (StockBalance $balance): bool => bccomp(
                (string) $balance->available_quantity,
                $quantity,
                3,
            ) >= 0)
            ->map(fn (StockBalance $balance): array => [
                'id' => $balance->stock_location_id,
                'locationCode' => $balance->stockLocation?->location_code,
                'zoneCode' => $balance->stockLocation?->zone_code,
                'availableQuantity' => (string) $balance->available_quantity,
            ]);
    }

    /**
     * Articles de stock du client, indexés par article de catalogue.
     *
     * @param  Collection<int, OrderLine>  $lines
     * @return Collection<string, StockItem>
     */
    private function stockItemsOf(Order $order, Collection $lines): Collection
    {
        $catalogItemIds = $lines->pluck('catalog_item_id')->filter()->unique()->all();

        if ($catalogItemIds === []) {
            return collect();
        }

        return StockItem::query()
            ->where('customer_id', $order->customer_id)
            ->whereIn('catalog_item_id', $catalogItemIds)
            ->get()
            ->keyBy('catalog_item_id');
    }

    /**
     * Lignes déjà sorties du stock.
     *
     * L'entité source du mouvement sert de clé d'idempotence : aucune colonne
     * supplémentaire n'est nécessaire, et un aller-retour brouillon → confirmée
     * ne préleve pas deux fois.
     *
     * @param  Collection<int, OrderLine>  $lines
     * @return list<string>
     */
    private function alreadyConsumed(Collection $lines): array
    {
        return StockMovement::query()
            ->where('source_entity_type', MorphMap::ORDER_LINE)
            ->whereIn('source_entity_id', $lines->pluck('id')->all())
            ->pluck('source_entity_id')
            ->all();
    }
}
