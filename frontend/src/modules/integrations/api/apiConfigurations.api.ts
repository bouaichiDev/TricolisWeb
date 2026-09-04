import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type {
  ApiConfiguration,
  ApiConfigurationFilters,
  ApiConfigurationPayload,
} from '../types/apiConfiguration'

export const apiConfigurationsApi = {
  list: (filters: ApiConfigurationFilters) =>
    api.get<ApiCollection<ApiConfiguration>>('/api-configurations', { query: { ...filters } }),

  create: (payload: ApiConfigurationPayload) =>
    api
      .post<ApiResource<ApiConfiguration>>('/api-configurations', payload)
      .then((response) => response.data),

  update: (id: string, payload: Partial<ApiConfigurationPayload>) =>
    api
      .patch<ApiResource<ApiConfiguration>>(`/api-configurations/${id}`, payload)
      .then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/api-configurations/${id}`),
}
