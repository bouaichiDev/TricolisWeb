import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Agency, AgencyFilters } from '../types/agency'

export interface AgencyPayload {
  code: string
  name: string
  shortName?: string | null
  email?: string | null
  phone?: string | null
  color?: string | null
  loadingPoint?: string | null
  status?: string
}

export const agenciesApi = {
  list: (filters: AgencyFilters) =>
    api.get<ApiCollection<Agency>>('/agencies', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<Agency>>(`/agencies/${id}`).then((response) => response.data),

  create: (payload: AgencyPayload) =>
    api.post<ApiResource<Agency>>('/agencies', payload).then((response) => response.data),

  update: (id: string, payload: Partial<AgencyPayload>) =>
    api.patch<ApiResource<Agency>>(`/agencies/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/agencies/${id}`),
}
