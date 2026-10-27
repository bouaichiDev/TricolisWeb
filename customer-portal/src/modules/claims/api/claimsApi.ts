import type { OrderDocument } from '@/modules/orders/types'
import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

import type { ClaimDetail, ClaimFilters, ClaimListItem, ClaimPayload } from '../types'

const data = async <T>(request: Promise<ApiEnvelope<T>>): Promise<T> => (await request).data

/** Réclamations du client courant : aucun identifiant de client n'est envoyé. */
export const claimsApi = {
  list: (filters: ClaimFilters) => api.get<Paginated<ClaimListItem>>('/claims', { query: { ...filters, perPage: filters.perPage ?? 20 } }),
  get: (id: string) => data(api.get<ApiEnvelope<ClaimDetail>>(`/claims/${id}`)),
  create: (payload: ClaimPayload) => data(api.post<ApiEnvelope<ClaimDetail>>('/claims', payload)),
  update: ({ id, ...payload }: Partial<ClaimPayload> & { id: string }) => data(api.patch<ApiEnvelope<ClaimDetail>>(`/claims/${id}`, payload)),

  documents: (id: string) => data(api.get<ApiEnvelope<OrderDocument[]>>(`/claims/${id}/documents`)),
  uploadDocument: ({ id, file }: { id: string; file: File }) => {
    const body = new FormData()
    body.append('file', file)

    return data(api.upload<ApiEnvelope<OrderDocument>>(`/claims/${id}/documents`, body))
  },
}
