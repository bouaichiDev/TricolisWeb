import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { Provider, ProviderFilters, ProviderPayload } from '../types/provider'

export const providersApi = {
  list: (filters: ProviderFilters) =>
    api.get<ApiCollection<Provider>>('/providers', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<Provider>>(`/providers/${id}`).then((response) => response.data),

  create: (payload: ProviderPayload) =>
    api.post<ApiResource<Provider>>('/providers', payload).then((response) => response.data),

  update: (id: string, payload: Partial<ProviderPayload>) =>
    api.patch<ApiResource<Provider>>(`/providers/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/providers/${id}`),
}
