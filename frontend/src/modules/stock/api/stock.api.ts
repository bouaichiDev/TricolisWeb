import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type {
  StockBalance,
  StockBalanceFilters,
  StockItem,
  StockItemFilters,
  StockLocation,
  StockLocationFilters,
  StockMovement,
  StockMovementFilters,
} from '../types/stock'

/** Charge utile relevée sur `StoreStockItemRequest`. */
export interface StockItemPayload {
  customerId: string
  catalogItemId?: string | null
  articleCode: string
  barcode?: string | null
  description?: string | null
  status: string
}

/**
 * Charge utile relevée sur `StoreStockMovementRequest`.
 *
 * `movementType` est une chaîne libre : le backend ne l'interprète pas, et
 * `CreateStockMovementAction` le dit — « aucun type de mouvement n'est
 * interprété : le diagramme n'en énumère aucun ».
 *
 * Ce qui est contrôlé est structurel : au moins une source ou une destination,
 * les deux différentes, et le même dépôt de part et d'autre.
 */
export interface StockMovementPayload {
  stockItemId: string
  sourceLocationId?: string | null
  destinationLocationId?: string | null
  movementType: string
  quantity: number
  sourceEntityType?: string | null
  sourceEntityId?: string | null
}

/** Charge utile relevée sur `StoreStockLocationRequest`. */
export interface StockLocationPayload {
  depotId: string
  parentLocationId?: string | null
  zoneCode?: string | null
  aisle?: string | null
  rack?: string | null
  level?: string | null
  locationCode: string
  barcode?: string | null
  status: string
}

export const stockApi = {
  items: (filters: StockItemFilters) =>
    api.get<ApiCollection<StockItem>>('/stock-items', { query: { ...filters } }),

  createItem: (customerId: string, payload: StockItemPayload) =>
    api
      .post<ApiResource<StockItem>>(`/customers/${customerId}/stock-items`, payload)
      .then((response) => response.data),

  balances: (filters: StockBalanceFilters) =>
    api.get<ApiCollection<StockBalance>>('/stock-balances', { query: { ...filters } }),

  movements: (filters: StockMovementFilters) =>
    api.get<ApiCollection<StockMovement>>('/stock-movements', { query: { ...filters } }),

  createMovement: (payload: StockMovementPayload) =>
    api
      .post<ApiResource<StockMovement>>('/stock-movements', payload)
      .then((response) => response.data),

  locations: (filters: StockLocationFilters) =>
    api.get<ApiCollection<StockLocation>>('/stock-locations', { query: { ...filters } }),

  createLocation: (payload: StockLocationPayload) =>
    api
      .post<ApiResource<StockLocation>>('/stock-locations', payload)
      .then((response) => response.data),

  updateLocation: (id: string, payload: Partial<StockLocationPayload>) =>
    api
      .patch<ApiResource<StockLocation>>(`/stock-locations/${id}`, payload)
      .then((response) => response.data),

  removeLocation: (id: string) => api.delete<void>(`/stock-locations/${id}`),
}
