/**
 * Filtres de liste, calqués sur les `List*Request` du serveur.
 *
 * Rien n'est ajouté : un paramètre absent des règles part en 422 et la liste
 * revient vide, sans que l'écran puisse dire pourquoi. Les tris suivent les
 * listes blanches `SORTABLE` des Queries, en `snake_case` — c'est le nom de
 * colonne que le serveur attend, pas le nom du champ JSON.
 */

export type SortDirection = 'asc' | 'desc'

interface BaseFilters {
  page: number
  perPage: number
  search?: string
  sort?: string
  direction?: SortDirection
  status?: string
}

/** `StockItemListQuery::SORTABLE`. */
export const STOCK_ITEM_SORTS = ['article_code', 'barcode', 'status'] as const

export interface StockItemFilters extends BaseFilters {
  customerId?: string
  catalogItemId?: string
  articleCode?: string
  barcode?: string
}

/** `StockLocationListQuery::SORTABLE`. */
export const STOCK_LOCATION_SORTS = [
  'zone_code',
  'aisle',
  'rack',
  'level',
  'location_code',
  'status',
] as const

export interface StockLocationFilters extends BaseFilters {
  depotId?: string
  parentLocationId?: string
  zoneCode?: string
  aisle?: string
  rack?: string
  level?: string
  locationCode?: string
  barcode?: string
}

/**
 * `StockBalanceListQuery::SORTABLE`.
 *
 * Pas de `search` : la Query n'en applique aucune. Un `search=` y serait accepté
 * par `ListRequest` puis ignoré, ce qui est pire qu'un champ absent — l'écran
 * paraîtrait chercher.
 */
export const STOCK_BALANCE_SORTS = [
  'quantity',
  'reserved_quantity',
  'available_quantity',
  'updated_at',
] as const

export interface StockBalanceFilters extends Omit<BaseFilters, 'search' | 'status'> {
  stockItemId?: string
  stockLocationId?: string
  customerId?: string
  availableOnly?: boolean
}

/** `StockMovementListQuery::SORTABLE`. */
export const STOCK_MOVEMENT_SORTS = ['created_at', 'quantity', 'movement_type'] as const

export interface StockMovementFilters extends Omit<BaseFilters, 'status'> {
  stockItemId?: string
  sourceLocationId?: string
  destinationLocationId?: string
  movementType?: string
  sourceEntityType?: string
  sourceEntityId?: string
  createdBy?: string
}

/** `StockReservationListQuery::SORTABLE`. */
export const STOCK_RESERVATION_SORTS = [
  'reserved_at',
  'released_at',
  'quantity',
  'status',
] as const

export interface StockReservationFilters extends Omit<BaseFilters, 'search'> {
  stockItemId?: string
  stockLocationId?: string
  orderLineId?: string
  reservedFrom?: string
  reservedTo?: string
  releasedFrom?: string
  releasedTo?: string
}
