import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type {
  ExportConfiguration,
  ExportConfigurationFilters,
  ExportConfigurationPayload,
} from '../types/export'

/**
 * Destinations d'export des clients.
 *
 * Une seule implémentation, partagée par la Facturation et par les
 * Intégrations : le §34 interdit une table `CustomerInvoiceExportConfiguration`
 * séparée, et le §77 interdit un second module. Les configurations de facture
 * de la Phase 6 sont exactement ces configurations-là.
 *
 * **Le mot de passe ne revient jamais.** Le serveur rend `hasPassword`, un
 * booléen ; le champ de saisie sert à le remplacer, pas à le relire. Son
 * absence de la charge utile signifie « inchangé » — une chaîne vide
 * l'effacerait.
 */
export const customerExportConfigurationsApi = {
  /** Liste globale, tous clients confondus — l'écran Intégrations. */
  list: (filters: ExportConfigurationFilters) =>
    api.get<ApiCollection<ExportConfiguration>>('/customer-export-configurations', {
      query: { ...filters },
    }),

  /**
   * Les destinations d'un client.
   *
   * Elles se lisent sous le client : une configuration appartient à un client
   * et n'a pas de sens hors de lui — le §113 veut d'ailleurs qu'elle ne serve
   * jamais à un autre.
   */
  byCustomer: (customerId: string) =>
    api.get<ApiCollection<ExportConfiguration>>(`/customers/${customerId}/export-configurations`),

  get: (id: string) =>
    api
      .get<ApiResource<ExportConfiguration>>(`/customer-export-configurations/${id}`)
      .then((r) => r.data),

  create: (payload: ExportConfigurationPayload) =>
    api
      .post<ApiResource<ExportConfiguration>>('/customer-export-configurations', payload)
      .then((r) => r.data),

  createForCustomer: (customerId: string, payload: ExportConfigurationPayload) =>
    api
      .post<ApiResource<ExportConfiguration>>(
        `/customers/${customerId}/export-configurations`,
        payload,
      )
      .then((r) => r.data),

  update: (id: string, payload: Partial<ExportConfigurationPayload>) =>
    api
      .patch<ApiResource<ExportConfiguration>>(`/customer-export-configurations/${id}`, payload)
      .then((r) => r.data),

  /** Refusée en conflit tant que des envois s'y rattachent (§71). */
  remove: (id: string) => api.delete<void>(`/customer-export-configurations/${id}`),
}
