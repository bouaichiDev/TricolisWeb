import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { Vehicle, VehicleFilters, VehiclePayload } from '../types/vehicle'

export const vehiclesApi = {
  list: (filters: VehicleFilters) =>
    api.get<ApiCollection<Vehicle>>('/vehicles', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<Vehicle>>(`/vehicles/${id}`).then((response) => response.data),

  create: (payload: VehiclePayload) =>
    api.post<ApiResource<Vehicle>>('/vehicles', payload).then((response) => response.data),

  update: (id: string, payload: Partial<VehiclePayload>) =>
    api.patch<ApiResource<Vehicle>>(`/vehicles/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/vehicles/${id}`),
}
