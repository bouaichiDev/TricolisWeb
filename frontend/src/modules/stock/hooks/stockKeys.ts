import type {
  StockBalanceFilters,
  StockItemFilters,
  StockLocationFilters,
  StockMovementFilters,
  StockReservationFilters,
} from '../types/stockFilters'

/**
 * Clés de cache du stock.
 *
 * Elles vivent dans leur propre fichier parce qu'une écriture en invalide
 * **plusieurs** : un mouvement touche l'historique, les soldes, la fiche de
 * l'article et le résumé du client ; une réservation y ajoute la ligne de
 * commande. Les hooks de mutation ont donc besoin des clés des hooks de
 * lecture, et l'inverse est faux — un fichier commun casse le cycle.
 *
 * Chaque famille expose une clé « racine » sans filtre : c'est elle qu'on
 * invalide, pour atteindre toutes les pages et tous les tris d'un coup.
 */
export const stockKeys = {
  all: ['stock'] as const,

  items: () => [...stockKeys.all, 'items'] as const,
  itemList: (filters: StockItemFilters) => [...stockKeys.items(), 'list', filters] as const,
  itemsOfCustomer: (customerId: string, filters: StockItemFilters) =>
    [...stockKeys.items(), 'customer', customerId, filters] as const,
  item: (id: string) => [...stockKeys.items(), 'detail', id] as const,

  locations: () => [...stockKeys.all, 'locations'] as const,
  locationList: (filters: StockLocationFilters) =>
    [...stockKeys.locations(), 'list', filters] as const,
  locationTree: (depotId: string | undefined) =>
    [...stockKeys.locations(), 'tree', depotId ?? null] as const,
  location: (id: string) => [...stockKeys.locations(), 'detail', id] as const,

  balances: () => [...stockKeys.all, 'balances'] as const,
  balanceList: (filters: StockBalanceFilters) =>
    [...stockKeys.balances(), 'list', filters] as const,
  balancesOfCustomer: (customerId: string, filters: StockBalanceFilters) =>
    [...stockKeys.balances(), 'customer', customerId, filters] as const,

  movements: () => [...stockKeys.all, 'movements'] as const,
  movementList: (filters: StockMovementFilters) =>
    [...stockKeys.movements(), 'list', filters] as const,
  movement: (id: string) => [...stockKeys.movements(), 'detail', id] as const,

  reservations: () => [...stockKeys.all, 'reservations'] as const,
  reservationList: (filters: StockReservationFilters) =>
    [...stockKeys.reservations(), 'list', filters] as const,
  reservation: (id: string) => [...stockKeys.reservations(), 'detail', id] as const,
}
