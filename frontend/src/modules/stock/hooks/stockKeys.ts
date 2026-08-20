import type {
  StockBalanceFilters,
  StockItemFilters,
  StockLocationFilters,
  StockMovementFilters,
} from '../types/stock'

/**
 * Clés de cache du stock.
 *
 * Elles vivent dans leur propre fichier parce qu'un mouvement invalide **trois**
 * listes à la fois : les soldes, l'historique, et l'article de stock lui-même
 * s'il vient d'être créé. Les hooks de mutation ont donc besoin des clés des
 * hooks de lecture, et l'inverse est faux.
 */
export const stockKeys = {
  all: ['stock'] as const,

  items: () => [...stockKeys.all, 'items'] as const,
  itemList: (filters: StockItemFilters) => [...stockKeys.items(), filters] as const,

  balances: () => [...stockKeys.all, 'balances'] as const,
  balanceList: (filters: StockBalanceFilters) => [...stockKeys.balances(), filters] as const,

  movements: () => [...stockKeys.all, 'movements'] as const,
  movementList: (filters: StockMovementFilters) => [...stockKeys.movements(), filters] as const,

  locations: () => [...stockKeys.all, 'locations'] as const,
  locationList: (filters: StockLocationFilters) => [...stockKeys.locations(), filters] as const,
}
