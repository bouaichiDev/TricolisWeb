import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Claim, ClaimFilters, ClaimPayload, ClaimUpdatePayload } from '../types/claim'

/**
 * Réclamations.
 *
 * Deux chemins de création coexistent côté serveur : `POST /claims`, qui exige
 * `customerId` dans la charge utile, et `POST /customers/{customer}/claims`, où
 * le client vient de l'URL. Le second est utilisé depuis une commande : le
 * client y est connu, et le §15 interdit de laisser choisir celui d'une autre
 * commande.
 */
export const claimsApi = {
  list: (filters: ClaimFilters) =>
    api.get<ApiCollection<Claim>>('/claims', { query: { ...filters } }),

  byOrder: (orderId: string, filters: ClaimFilters) =>
    api.get<ApiCollection<Claim>>(`/orders/${orderId}/claims`, { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<Claim>>(`/claims/${id}`).then((response) => response.data),

  createForCustomer: (customerId: string, payload: Omit<ClaimPayload, 'customerId'>) =>
    api
      .post<ApiResource<Claim>>(`/customers/${customerId}/claims`, payload)
      .then((response) => response.data),

  update: (id: string, payload: ClaimUpdatePayload) =>
    api.patch<ApiResource<Claim>>(`/claims/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/claims/${id}`),
}
