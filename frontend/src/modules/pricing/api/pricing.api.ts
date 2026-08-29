import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type {
  FormulaCheck,
  PrebillingFilters,
  PrebillingService,
  PriceList,
  PriceListFilters,
  PriceListPayload,
  PriceMatrix,
  PriceMatrixPayload,
  PriceRule,
  PriceRulePayload,
} from '../types/pricing'

export const pricingApi = {
  lists: (filters: PriceListFilters) =>
    api.get<ApiCollection<PriceList>>('/price-lists', { query: { ...filters } }),

  list: (id: string) => api.get<ApiResource<PriceList>>(`/price-lists/${id}`).then((r) => r.data),

  createList: (payload: PriceListPayload) =>
    api.post<ApiResource<PriceList>>('/price-lists', payload).then((r) => r.data),

  updateList: (id: string, payload: Partial<PriceListPayload>) =>
    api.patch<ApiResource<PriceList>>(`/price-lists/${id}`, payload).then((r) => r.data),

  removeList: (id: string) => api.delete<void>(`/price-lists/${id}`),

  /**
   * Une règle et ses conditions partent ensemble.
   *
   * Elles n'existent pas sans elle : les envoyer séparément demanderait trois
   * appels pour une seule pensée — « la livraison, entre 1144 et 4000 ».
   */
  createRule: (priceListId: string, payload: PriceRulePayload) =>
    api
      .post<ApiResource<PriceRule>>(`/price-lists/${priceListId}/rules`, payload)
      .then((r) => r.data),

  updateRule: (id: string, payload: Partial<PriceRulePayload>) =>
    api.patch<ApiResource<PriceRule>>(`/price-rules/${id}`, payload).then((r) => r.data),

  removeRule: (id: string) => api.delete<void>(`/price-rules/${id}`),

  createMatrix: (priceListId: string, payload: PriceMatrixPayload) =>
    api
      .post<ApiResource<PriceMatrix>>(`/price-lists/${priceListId}/matrices`, payload)
      .then((r) => r.data),

  updateMatrix: (id: string, payload: Partial<PriceMatrixPayload>) =>
    api.patch<ApiResource<PriceMatrix>>(`/price-matrices/${id}`, payload).then((r) => r.data),

  removeMatrix: (id: string) => api.delete<void>(`/price-matrices/${id}`),

  /**
   * Vérifier une formule, et l'essayer.
   *
   * **Le même moteur que le calcul réel.** Un évaluateur écrit en JavaScript
   * finirait par diverger, et l'écran annoncerait un prix que la facture ne
   * confirmerait pas.
   */
  checkFormula: (formula: string, variables?: Record<string, number | null>) =>
    api
      .post<ApiResource<FormulaCheck>>('/pricing/formulas/validate', { formula, variables })
      .then((r) => r.data),

  /** Ce qui reste à facturer, avec le tarif que le barème donnerait. */
  prebilling: (filters: PrebillingFilters) =>
    api.get<ApiCollection<PrebillingService>>('/pricing/prebilling', { query: { ...filters } }),
}
