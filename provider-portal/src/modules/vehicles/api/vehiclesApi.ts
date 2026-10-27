import type { ResourceFilters } from '@/modules/drivers/types'
import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

import type { Vehicle, VehiclePayload, VehicleType } from '../types'

/** Aucun identifiant de fournisseur n'est envoyé : le serveur le lit dans le jeton. */
export const vehiclesApi = {
  list: (filters: ResourceFilters) =>
    api.get<Paginated<Vehicle>>('/vehicles', {
      query: { search: filters.search, status: filters.status, page: filters.page, perPage: filters.perPage },
    }),

  types: async () => (await api.get<ApiEnvelope<VehicleType[]>>('/vehicle-types')).data,

  create: async (payload: VehiclePayload) => (await api.post<ApiEnvelope<Vehicle>>('/vehicles', payload)).data,

  update: async (id: string, payload: VehiclePayload) => (await api.patch<ApiEnvelope<Vehicle>>(`/vehicles/${id}`, payload)).data,

  setActive: async (id: string, active: boolean) =>
    (await api.post<ApiEnvelope<Vehicle>>(`/vehicles/${id}/${active ? 'activate' : 'deactivate'}`)).data,
}
