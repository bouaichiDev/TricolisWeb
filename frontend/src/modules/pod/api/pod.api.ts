import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type {
  ProofOfDelivery,
  ProofOfDeliveryFilters,
  ProofOfDeliveryPayload,
} from '../types/proofOfDelivery'

/**
 * Preuves de livraison.
 *
 * Comme le suivi, ni `update` ni `destroy` n'existent : une preuve constate un
 * fait, elle ne se retouche pas. Une livraison mal constatée se corrige par une
 * réclamation, pas en réécrivant la preuve.
 */
export const podApi = {
  byOrder: (orderId: string, filters: ProofOfDeliveryFilters) =>
    api.get<ApiCollection<ProofOfDelivery>>(`/orders/${orderId}/proofs-of-delivery`, {
      query: { ...filters },
    }),

  /** Le détail charge `signatureDocument` et `photoDocument`, la liste non. */
  get: (id: string) =>
    api
      .get<ApiResource<ProofOfDelivery>>(`/proofs-of-delivery/${id}`)
      .then((response) => response.data),

  create: (orderId: string, payload: ProofOfDeliveryPayload) =>
    api
      .post<ApiResource<ProofOfDelivery>>(`/orders/${orderId}/proofs-of-delivery`, payload)
      .then((response) => response.data),
}
