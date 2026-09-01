import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type {
  ImportPreview,
  CustomerImportConfiguration,
  CustomerImportConfigurationFilters,
  CustomerImportConfigurationPayload,
} from '../types/customerIntegration'

/**
 * Configurations d'import client.
 *
 * **Aucune route d'exécution.** Le §17 est explicite : ne pas en créer une qui
 * n'existe pas. Le modèle officiel décrit comment lire un fichier ; il ne
 * contient ni `Import`, ni `ImportRow`, ni `ImportError`, et le backend n'expose
 * donc rien pour déclencher ou historiser une lecture.
 */
export const customerImportConfigurationsApi = {
  list: (filters: CustomerImportConfigurationFilters) =>
    api.get<ApiCollection<CustomerImportConfiguration>>('/customer-import-configurations', {
      query: { ...filters },
    }),

  byCustomer: (customerId: string, filters: CustomerImportConfigurationFilters) =>
    api.get<ApiCollection<CustomerImportConfiguration>>(
      `/customers/${customerId}/import-configurations`,
      { query: { ...filters } },
    ),

  get: (id: string) =>
    api
      .get<ApiResource<CustomerImportConfiguration>>(`/customer-import-configurations/${id}`)
      .then((response) => response.data),

  create: (payload: CustomerImportConfigurationPayload) =>
    api
      .post<ApiResource<CustomerImportConfiguration>>(
        '/customer-import-configurations',
        payload,
      )
      .then((response) => response.data),

  /**
   * Création depuis la fiche d'un client déjà ouvert.
   *
   * La route imbriquée vérifie l'appartenance du client à l'organisation avant
   * de valider le formulaire : un identifiant étranger sort en 404 plutôt qu'en
   * 422 sur un champ.
   */
  createForCustomer: (customerId: string, payload: CustomerImportConfigurationPayload) =>
    api
      .post<ApiResource<CustomerImportConfiguration>>(
        `/customers/${customerId}/import-configurations`,
        payload,
      )
      .then((response) => response.data),

  /** `customerId` est absent : une configuration ne change pas de client. */
  update: (
    id: string,
    payload: Partial<Omit<CustomerImportConfigurationPayload, 'customerId'>>,
  ) =>
    api
      .patch<ApiResource<CustomerImportConfiguration>>(
        `/customer-import-configurations/${id}`,
        payload,
      )
      .then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/customer-import-configurations/${id}`),

  /**
   * Éprouve la correspondance sur un fichier, sans rien créer.
   *
   * Le fichier n'est pas stocké : il est lu en mémoire, la correspondance
   * appliquée, le résultat rendu. C'est la seule façon de vérifier qu'une
   * correspondance est juste avant de s'en servir.
   */
  preview: (id: string, file: File) => {
    const form = new FormData()
    form.append('file', file)

    return api
      .upload<ApiResource<ImportPreview>>(
        `/customer-import-configurations/${id}/preview`,
        form,
      )
      .then((response) => response.data)
  },
}
