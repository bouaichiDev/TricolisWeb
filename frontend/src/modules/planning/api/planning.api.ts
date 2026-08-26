import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { PlanningResult, PoolFilters, PoolOrder, UnplanningResult } from '../types/pool'

export const planningApi = {
  /** Ce qui attend une tournée : une lecture des commandes, pas une table. */
  pool: (filters: PoolFilters) =>
    api.get<ApiCollection<PoolOrder>>('/planning/pool', { query: { ...filters } }),

  /**
   * Glisser une commande ou des services dans une tournée.
   *
   * Un seul appel : le serveur planifie ce qu'il peut et rend les refusés avec
   * leur motif.
   */
  plan: (tourId: string, payload: { orderIds?: string[]; orderServiceIds?: string[] }) =>
    api
      .post<ApiResource<PlanningResult & { tour: unknown }>>(`/tours/${tourId}/plan`, payload)
      .then((response) => response.data),

  /**
   * Retirer une commande ou des services d'une tournée.
   *
   * Symétrique de `plan`. Le serveur refuse une tournée terminée : ce qui a
   * été livré ne retourne pas dans le pool.
   */
  unplan: (tourId: string, payload: { orderIds?: string[]; orderServiceIds?: string[] }) =>
    api
      .post<ApiResource<UnplanningResult & { tour: unknown }>>(`/tours/${tourId}/unplan`, payload)
      .then((response) => response.data),
}
