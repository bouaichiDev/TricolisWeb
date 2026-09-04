import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Depot } from '../types/depot'

export interface DepotPayload {
  code: string
  name: string
  status?: string
}

/**
 * Toutes les routes sont imbriquees sous l'agence.
 *
 * Il n'existe **pas** de `GET /depots` global : le backend n'expose que
 * `/agencies/{agency}/depots`. C'est ce qui impose de choisir une agence avant
 * de voir des depots, et qui garantit qu'un depot ne peut pas etre rattache a
 * l'agence d'une autre organisation.
 */
export const depotsApi = {
  list: (agencyId: string, params: { page?: number; perPage?: number; search?: string }) =>
    api.get<ApiCollection<Depot>>(`/agencies/${agencyId}/depots`, { query: params }),

  get: (agencyId: string, depotId: string) =>
    api
      .get<ApiResource<Depot>>(`/agencies/${agencyId}/depots/${depotId}`)
      .then((response) => response.data),

  create: (agencyId: string, payload: DepotPayload) =>
    api
      .post<ApiResource<Depot>>(`/agencies/${agencyId}/depots`, payload)
      .then((response) => response.data),

  update: (agencyId: string, depotId: string, payload: Partial<DepotPayload>) =>
    api
      .patch<ApiResource<Depot>>(`/agencies/${agencyId}/depots/${depotId}`, payload)
      .then((response) => response.data),

  remove: (agencyId: string, depotId: string) =>
    api.delete<void>(`/agencies/${agencyId}/depots/${depotId}`),
}
