import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type {
  TypeItem,
  TypeItemFilters,
  TypeItemPayload,
  TypeSource,
  TypeSourcePayload,
} from '../types/type'

/** Les sources : véhicule, colis, groupage, et celles de l'organisme. */
export const typeSourcesApi = {
  list: (search?: string) =>
    api.get<ApiCollection<TypeSource>>('/types', {
      query: { page: 1, perPage: 100, search },
    }),

  create: (payload: TypeSourcePayload) =>
    api.post<ApiResource<TypeSource>>('/types', payload).then((response) => response.data),

  update: (id: string, payload: Partial<TypeSourcePayload>) =>
    api.patch<ApiResource<TypeSource>>(`/types/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/types/${id}`),
}

/** Les valeurs d'une source. */
export const typeItemsApi = {
  list: (filters: TypeItemFilters) =>
    api.get<ApiCollection<TypeItem>>('/type-items', { query: { ...filters } }),

  create: (payload: TypeItemPayload) =>
    api.post<ApiResource<TypeItem>>('/type-items', payload).then((response) => response.data),

  update: (id: string, payload: Partial<Omit<TypeItemPayload, 'typeId'>>) =>
    api.patch<ApiResource<TypeItem>>(`/type-items/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/type-items/${id}`),
}
