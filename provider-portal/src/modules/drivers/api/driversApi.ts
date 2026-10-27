import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

import type { Driver, DriverPayload, ResourceFilters } from '../types'

/** Aucun identifiant de fournisseur n'est envoyé : le serveur le lit dans le jeton. */
export const driversApi = {
  list: (filters: ResourceFilters) =>
    api.get<Paginated<Driver>>('/drivers', {
      query: { search: filters.search, status: filters.status, page: filters.page, perPage: filters.perPage },
    }),

  create: async (payload: DriverPayload) => (await api.post<ApiEnvelope<Driver>>('/drivers', payload)).data,

  update: async (id: string, payload: Omit<DriverPayload, 'email'>) =>
    (await api.patch<ApiEnvelope<Driver>>(`/drivers/${id}`, payload)).data,

  setActive: async (id: string, active: boolean) =>
    (await api.post<ApiEnvelope<Driver>>(`/drivers/${id}/${active ? 'activate' : 'deactivate'}`)).data,
}
