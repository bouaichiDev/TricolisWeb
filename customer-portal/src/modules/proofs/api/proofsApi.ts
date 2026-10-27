import { api } from '@/shared/api/client'
import type { ApiEnvelope } from '@/shared/api/types'

import type { ProofOfDelivery } from '../types'

const data = async <T>(request: Promise<ApiEnvelope<T>>): Promise<T> => (await request).data

/** Preuves de livraison du client : lecture seule, aucune écriture n'existe. */
export const proofsApi = {
  byOrder: (orderId: string) => data(api.get<ApiEnvelope<ProofOfDelivery[]>>(`/orders/${orderId}/proofs-of-delivery`)),
  byService: (orderId: string, serviceId: string) =>
    data(api.get<ApiEnvelope<ProofOfDelivery[]>>(`/orders/${orderId}/services/${serviceId}/proofs-of-delivery`)),
}
