import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { StockLocation, StockLocationDetail, StockLocationTreeNode } from '../types/stock'
import type { StockLocationFilters } from '../types/stockFilters'

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

/**
 * `UpdateStockLocationRequest` ne connaît pas `depotId`.
 *
 * Un emplacement est **physique** : il ne déménage pas d'un dépôt à l'autre. Le
 * champ est absent des règles de mise à jour, et l'unicité de `locationCode` est
 * d'ailleurs contrôlée dans le dépôt d'origine.
 */
export type StockLocationUpdatePayload = Partial<Omit<StockLocationPayload, 'depotId'>>

export const stockLocationsApi = {
  list: (filters: StockLocationFilters) =>
    api.get<ApiCollection<StockLocation>>('/stock-locations', { query: { ...filters } }),

  /**
   * Hiérarchie complète, non paginée.
   *
   * `StockLocationListQuery::tree()` charge **tous** les emplacements de
   * l'organisation en une requête puis les regroupe en mémoire — pas de N+1,
   * mais pas de pagination non plus. D'où `depotId` : un entrepôt entier tient,
   * un parc de dépôts beaucoup moins.
   */
  tree: (depotId?: string) =>
    api
      .get<ApiResource<StockLocationTreeNode[]>>('/stock-locations/tree', {
        query: { depotId },
      })
      .then((response) => response.data),

  get: (id: string) =>
    api
      .get<ApiResource<StockLocationDetail>>(`/stock-locations/${id}`)
      .then((response) => response.data),

  create: (payload: StockLocationPayload) =>
    api
      .post<ApiResource<StockLocationDetail>>('/stock-locations', payload)
      .then((response) => response.data),

  update: (id: string, payload: StockLocationUpdatePayload) =>
    api
      .patch<ApiResource<StockLocationDetail>>(`/stock-locations/${id}`, payload)
      .then((response) => response.data),

  /** 409 tant qu'un enfant, un solde, une réservation ou un colis s'y trouve. */
  remove: (id: string) => api.delete<void>(`/stock-locations/${id}`),
}
