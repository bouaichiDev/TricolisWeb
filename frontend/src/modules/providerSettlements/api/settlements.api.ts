import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type {
  ProviderSettlement,
  ProviderSettlementDetail,
  SettleableService,
  SettleableServiceFilters,
  SettlementFilters,
  SettlementPayload,
} from '../types/settlement'

export const settlementsApi = {
  list: (filters: SettlementFilters) =>
    api.get<ApiCollection<ProviderSettlement>>('/provider-settlements', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<ProviderSettlementDetail>>(`/provider-settlements/${id}`).then((r) => r.data),

  /**
   * Créer le décompte sous son fournisseur.
   *
   * L'API n'expose pas de création globale : un décompte appartient à un
   * fournisseur, et le désigner dans l'URL évite d'avoir à faire confiance au
   * corps de la requête pour savoir qui l'on paie.
   */
  createFor: (providerId: string, payload: SettlementPayload) =>
    api
      .post<ApiResource<ProviderSettlementDetail>>(`/providers/${providerId}/settlements`, payload)
      .then((r) => r.data),

  remove: (id: string) => api.delete<void>(`/provider-settlements/${id}`),

  removeLine: (settlementId: string, lineId: string) =>
    api.delete<void>(`/provider-settlements/${settlementId}/lines/${lineId}`),

  /**
   * Les prestations qu'il reste à régler à ce fournisseur.
   *
   * Le serveur ne retient que celles dont **l'affectation active** est chez lui
   * (§17) : une tentative échouée passée par un autre fournisseur ne se paie
   * pas deux fois.
   */
  settleableServices: (providerId: string, filters: SettleableServiceFilters) =>
    api.get<ApiCollection<SettleableService>>(`/providers/${providerId}/settleable-services`, {
      query: { ...filters },
    }),
}
