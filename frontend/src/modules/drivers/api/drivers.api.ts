import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { Driver, DriverFilters, DriverPayload } from '../types/driver'

export const driversApi = {
  list: (filters: DriverFilters) =>
    api.get<ApiCollection<Driver>>('/drivers', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<Driver>>(`/drivers/${id}`).then((response) => response.data),

  create: (payload: DriverPayload) =>
    api.post<ApiResource<Driver>>('/drivers', payload).then((response) => response.data),

  update: (id: string, payload: Partial<DriverPayload>) =>
    api.patch<ApiResource<Driver>>(`/drivers/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/drivers/${id}`),
}
