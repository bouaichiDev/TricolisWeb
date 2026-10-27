import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

import type { OrderPayload } from '../form/orderSchema'
import type {
  CatalogItem,
  FormOptions,
  HistoryEntry,
  OrderActions,
  OrderDetail,
  OrderDocument,
  OrderFilters,
  OrderListItem,
} from '../types'

const data = async <T>(request: Promise<ApiEnvelope<T>>): Promise<T> => (await request).data

/**
 * Commandes du client courant. Aucun identifiant de client n'est envoyé :
 * le backend l'impose depuis le jeton.
 */
export const ordersApi = {
  list: (filters: OrderFilters) =>
    api.get<Paginated<OrderListItem>>('/orders', {
      query: { ...filters, status: filters.status ? [filters.status] : undefined, perPage: filters.perPage ?? 20 },
    }),
  get: (id: string) => data(api.get<ApiEnvelope<OrderDetail>>(`/orders/${id}`)),
  create: (payload: OrderPayload) => data(api.post<ApiEnvelope<OrderDetail>>('/orders', payload)),
  update: (id: string, payload: OrderPayload & { updatedAt: string }) =>
    data(api.put<ApiEnvelope<OrderDetail>>(`/orders/${id}`, payload)),
  duplicate: (id: string) => data(api.post<ApiEnvelope<{ id: string; reference: string }>>(`/orders/${id}/duplicate`)),
  cancel: (id: string, reason: string) =>
    data(api.post<ApiEnvelope<{ id: string; status: string; actions: OrderActions }>>(`/orders/${id}/cancel`, { reason })),

  documents: (id: string) => data(api.get<ApiEnvelope<OrderDocument[]>>(`/orders/${id}/documents`)),
  uploadDocument: (id: string, file: File) => {
    const body = new FormData()
    body.append('file', file)

    return data(api.upload<ApiEnvelope<OrderDocument>>(`/orders/${id}/documents`, body))
  },
  downloadDocument: (id: string, documentId: string) => api.blob(`/orders/${id}/documents/${documentId}/download`),
  /** Documents d'un service — dont son bon de livraison (`isDeliveryNote`). */
  serviceDocuments: (id: string, serviceId: string) => data(api.get<ApiEnvelope<OrderDocument[]>>(`/orders/${id}/services/${serviceId}/documents`)),
  history: (id: string, page = 1) => api.get<Paginated<HistoryEntry>>(`/orders/${id}/history`, { query: { page } }),

  formOptions: () => data(api.get<ApiEnvelope<FormOptions>>('/order-form-options')),
  catalogItems: (search: string) => data(api.get<ApiEnvelope<CatalogItem[]>>('/catalog-items', { query: { search } })),
}
