import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type {
  ExportConfiguration,
  ExportConfigurationPayload,
  ExportJob,
  ExportJobFilters,
} from '../types/export'

export const exportsApi = {
  /**
   * Les destinations d'un client.
   *
   * Elles se lisent sous le client : une configuration appartient à un client
   * et n'a pas de sens hors de lui — le §113 veut d'ailleurs qu'elle ne serve
   * jamais à un autre.
   */
  configurations: (customerId: string) =>
    api.get<ApiCollection<ExportConfiguration>>(`/customers/${customerId}/export-configurations`),

  createConfiguration: (customerId: string, payload: ExportConfigurationPayload) =>
    api
      .post<ApiResource<ExportConfiguration>>(
        `/customers/${customerId}/export-configurations`,
        payload,
      )
      .then((r) => r.data),

  updateConfiguration: (id: string, payload: Partial<ExportConfigurationPayload>) =>
    api
      .patch<ApiResource<ExportConfiguration>>(`/customer-export-configurations/${id}`, payload)
      .then((r) => r.data),

  removeConfiguration: (id: string) => api.delete<void>(`/customer-export-configurations/${id}`),

  /** L'historique des envois. */
  jobs: (filters: ExportJobFilters) =>
    api.get<ApiCollection<ExportJob>>('/export-jobs', { query: { ...filters } }),

  /**
   * Relancer un envoi manqué.
   *
   * Le statut est fourni par l'appelant : le diagramme n'en énumère aucun pour
   * un envoi, et le référentiel les porte. Un envoi déjà transmis est refusé en
   * 409 — le renvoyer donnerait au client deux fois la même facture.
   */
  retryJob: (id: string) =>
    api.post<ApiResource<ExportJob>>(`/export-jobs/${id}/retry`, { status: 'pending' }),
}
