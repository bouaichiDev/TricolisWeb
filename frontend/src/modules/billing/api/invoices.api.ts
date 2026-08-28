import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type {
  BillableService,
  BillableServiceFilters,
  Invoice,
  InvoiceClosurePreview,
  InvoiceClosureResult,
  InvoiceDetail,
  InvoiceFilters,
  InvoiceLinePayload,
  InvoicePayload,
} from '../types/invoice'

export const invoicesApi = {
  list: (filters: InvoiceFilters) =>
    api.get<ApiCollection<Invoice>>('/invoices', { query: { ...filters } }),

  get: (id: string) => api.get<ApiResource<InvoiceDetail>>(`/invoices/${id}`).then((r) => r.data),

  create: (payload: InvoicePayload) =>
    api.post<ApiResource<InvoiceDetail>>('/invoices', payload).then((r) => r.data),

  update: (id: string, payload: Partial<InvoicePayload>) =>
    api.patch<ApiResource<InvoiceDetail>>(`/invoices/${id}`, payload).then((r) => r.data),

  remove: (id: string) => api.delete<void>(`/invoices/${id}`),

  addLine: (invoiceId: string, payload: InvoiceLinePayload) =>
    api.post<ApiResource<unknown>>(`/invoices/${invoiceId}/lines`, payload),

  removeLine: (invoiceId: string, lineId: string) =>
    api.delete<void>(`/invoices/${invoiceId}/lines/${lineId}`),

  /**
   * Ce que la clôture déclenchera, avant de la confirmer.
   *
   * Le §52 le veut : savoir où la facture partira, et si elle peut seulement
   * être clôturée, avant de la figer.
   */
  closurePreview: (id: string) =>
    api.get<ApiResource<InvoiceClosurePreview>>(`/invoices/${id}/closure`).then((r) => r.data),

  /**
   * Clôturer la facture.
   *
   * Il n'existe pas d'action « envoyer » : le §24 la refuse. L'envoi suit la
   * clôture, et la réponse dit ce qui est parti.
   */
  close: (id: string) =>
    api.post<ApiResource<InvoiceClosureResult>>(`/invoices/${id}/close`).then((r) => r.data),

  /**
   * Les prestations qu'il reste à facturer chez un client.
   *
   * L'éligibilité est décidée par le serveur (§42) : cet écran affiche ce
   * qu'on lui propose, il ne la recalcule pas.
   */
  billableServices: (customerId: string, filters: BillableServiceFilters) =>
    api.get<ApiCollection<BillableService>>(`/customers/${customerId}/billable-services`, {
      query: { ...filters },
    }),
}
