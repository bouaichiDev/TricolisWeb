import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { StockItem, StockItemDetail } from '../types/stock'
import type { StockItemFilters } from '../types/stockFilters'

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
 * `UpdateStockItemRequest` ne connaît pas `customerId`.
 *
 * Un article de stock **ne change pas de client** : ses soldes, ses mouvements
 * et ses réservations appartiennent à ce client, et les déplacer n'aurait pas de
 * sens comptable. Le champ est absent des règles de mise à jour, pas seulement
 * optionnel.
 */
export type StockItemUpdatePayload = Partial<Omit<StockItemPayload, 'customerId'>>

export const stockItemsApi = {
  list: (filters: StockItemFilters) =>
    api.get<ApiCollection<StockItem>>('/stock-items', { query: { ...filters } }),

  byCustomer: (customerId: string, filters: StockItemFilters) =>
    api.get<ApiCollection<StockItem>>(`/customers/${customerId}/stock-items`, {
      query: { ...filters },
    }),

  get: (id: string) =>
    api.get<ApiResource<StockItemDetail>>(`/stock-items/${id}`).then((response) => response.data),

  /**
   * Création, le client venant du formulaire.
   *
   * C'est la route à utiliser quand le client se choisit à l'écran : elle ne lit
   * `customerId` qu'à un seul endroit, le corps.
   */
  create: (payload: StockItemPayload) =>
    api.post<ApiResource<StockItemDetail>>('/stock-items', payload).then((r) => r.data),

  /**
   * Création depuis un client déjà ouvert.
   *
   * La route imbriquée vérifie l'appartenance du client à l'organisation
   * **avant** de valider le reste du formulaire — un identifiant étranger sort
   * en 404 plutôt qu'en 422 sur un champ.
   *
   * Attention : `storeForCustomer` construit l'article à partir du `customerId`
   * du **corps**, pas de celui de l'URL. Les deux doivent donc coïncider, et
   * c'est l'appelant qui s'en assure.
   */
  createForCustomer: (customerId: string, payload: StockItemPayload) =>
    api
      .post<ApiResource<StockItemDetail>>(`/customers/${customerId}/stock-items`, payload)
      .then((response) => response.data),

  update: (id: string, payload: StockItemUpdatePayload) =>
    api
      .patch<ApiResource<StockItemDetail>>(`/stock-items/${id}`, payload)
      .then((response) => response.data),

  /** 409 tant qu'un solde, un mouvement ou une réservation s'y rattache. */
  remove: (id: string) => api.delete<void>(`/stock-items/${id}`),
}
