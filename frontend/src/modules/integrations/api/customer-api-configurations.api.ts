import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type {
  CustomerApiConfiguration,
  CustomerApiConfigurationFilters,
  CustomerApiConfigurationPayload,
  CustomerApiKeyIssued,
} from '../types/customerIntegration'

/**
 * Accès API des clients — les clés **avec lesquelles ils nous appellent**.
 *
 * Deux méthodes seulement renvoient une clé en clair : `create` et `rotateKey`.
 * Aucune lecture ne la redonne, et il n'existe pas de route pour la relire :
 * le serveur n'en garde que le hash.
 *
 * Le résultat de ces deux appels **ne doit pas être mis en cache**. Les hooks
 * qui les emploient le passent directement au dialogue qui l'affiche, et il
 * disparaît à la fermeture.
 */
export const customerApiConfigurationsApi = {
  list: (filters: CustomerApiConfigurationFilters) =>
    api.get<ApiCollection<CustomerApiConfiguration>>('/customer-api-configurations', {
      query: { ...filters },
    }),

  byCustomer: (customerId: string, filters: CustomerApiConfigurationFilters) =>
    api.get<ApiCollection<CustomerApiConfiguration>>(
      `/customers/${customerId}/api-configurations`,
      { query: { ...filters } },
    ),

  get: (id: string) =>
    api
      .get<ApiResource<CustomerApiConfiguration>>(`/customer-api-configurations/${id}`)
      .then((response) => response.data),

  /** Renvoie la clé en clair — une seule fois, jamais relisible ensuite. */
  create: (payload: CustomerApiConfigurationPayload) =>
    api
      .post<ApiResource<CustomerApiKeyIssued>>('/customer-api-configurations', payload)
      .then((response) => response.data),

  createForCustomer: (customerId: string, payload: CustomerApiConfigurationPayload) =>
    api
      .post<ApiResource<CustomerApiKeyIssued>>(
        `/customers/${customerId}/api-configurations`,
        payload,
      )
      .then((response) => response.data),

  /**
   * `apiKeyHash` n'est jamais demandé, et `customerId` ne se modifie pas :
   * un accès appartient au client pour lequel il a été émis.
   */
  update: (
    id: string,
    payload: Partial<Omit<CustomerApiConfigurationPayload, 'customerId'>>,
  ) =>
    api
      .patch<ApiResource<CustomerApiConfiguration>>(
        `/customer-api-configurations/${id}`,
        payload,
      )
      .then((response) => response.data),

  /**
   * Renouvelle la clé : l'ancienne cesse immédiatement de fonctionner.
   *
   * Il n'existe aucun historique des clés — ni `ApiKeyHistory`, ni
   * `PreviousKeys`, ni `ApiToken` (§25). Une clé remplacée n'existe plus.
   */
  rotateKey: (id: string) =>
    api
      .post<ApiResource<CustomerApiKeyIssued>>(
        `/customer-api-configurations/${id}/rotate-key`,
        {},
      )
      .then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/customer-api-configurations/${id}`),
}
