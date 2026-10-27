import { api } from '@/shared/api/client'
import type { ApiEnvelope } from '@/shared/api/types'

import type { OrderTracking, PublicLinkState } from '../types'

const data = async <T>(request: Promise<ApiEnvelope<T>>): Promise<T> => (await request).data

/** Suivi d'une commande du client courant, et son lien public. */
export const trackingApi = {
  get: (orderId: string) => data(api.get<ApiEnvelope<OrderTracking>>(`/orders/${orderId}/tracking`)),

  publicLink: (orderId: string) => data(api.get<ApiEnvelope<PublicLinkState>>(`/orders/${orderId}/public-tracking-link`)),
  generateLink: ({ orderId, expiresAt }: { orderId: string; expiresAt?: string }) =>
    data(api.post<ApiEnvelope<PublicLinkState>>(`/orders/${orderId}/public-tracking-link`, expiresAt ? { expiresAt } : {})),
  revokeLink: (orderId: string) => data(api.delete<ApiEnvelope<PublicLinkState>>(`/orders/${orderId}/public-tracking-link`)),
}
