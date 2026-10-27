import type { CatalogItem } from '@/modules/orders/types'
import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

import type { StockBalance, StockFilterOptions, StockFilters, StockItem, StockItemPayload, StockMovement, StockReservation, StockSummary } from '../types'

const data = async <T>(request: Promise<ApiEnvelope<T>>): Promise<T> => (await request).data

const query = (filters: StockFilters) => ({ ...filters, status: filters.status ? [filters.status] : undefined, perPage: filters.perPage ?? 20 })

/**
 * Stock du client courant, en lecture — seuls les articles s'écrivent, jamais
 * une quantité. Aucun identifiant de client n'est envoyé.
 */
export const stockApi = {
  summary: () => data(api.get<ApiEnvelope<StockSummary>>('/stock/summary')),
  items: (filters: StockFilters) =>
    api.get<Paginated<StockItem> & { meta: { filters: StockFilterOptions } }>('/stock/items', { query: query(filters) }),
  item: (id: string) => data(api.get<ApiEnvelope<StockItem>>(`/stock/items/${id}`)),
  balances: (id: string) => data(api.get<ApiEnvelope<StockBalance[]>>(`/stock/items/${id}/balances`)),
  createItem: (payload: StockItemPayload) => data(api.post<ApiEnvelope<StockItem>>('/stock/items', payload)),
  updateItem: ({ id, ...payload }: StockItemPayload & { id: string }) => data(api.patch<ApiEnvelope<StockItem>>(`/stock/items/${id}`, payload)),
  catalogItems: (search: string) => data(api.get<ApiEnvelope<CatalogItem[]>>('/stock/catalog-items', { query: { search } })),

  movements: (filters: StockFilters) => api.get<Paginated<StockMovement>>('/stock/movements', { query: query(filters) }),
  reservations: (filters: StockFilters) => api.get<Paginated<StockReservation>>('/stock/reservations', { query: query(filters) }),
}
