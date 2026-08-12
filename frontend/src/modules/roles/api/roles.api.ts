import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Permission, Role, RoleFilters } from '../types/role'

export interface RoleCreatePayload {
  code: string
  name: string
  scope?: string | null
  isSystem: boolean
  status: string
  permissionIds?: string[]
}

/** `code` et `isSystem` sont absents : `UpdateRoleRequest` ne les accepte pas. */
export interface RoleUpdatePayload {
  name?: string
  scope?: string | null
  status?: string
  permissionIds?: string[]
}

export const rolesApi = {
  list: (filters: RoleFilters) => api.get<ApiCollection<Role>>('/roles', { query: { ...filters } }),

  get: (id: string) => api.get<ApiResource<Role>>(`/roles/${id}`).then((r) => r.data),

  create: (payload: RoleCreatePayload) =>
    api.post<ApiResource<Role>>('/roles', payload).then((r) => r.data),

  update: (id: string, payload: RoleUpdatePayload) =>
    api.patch<ApiResource<Role>>(`/roles/${id}`, payload).then((r) => r.data),

  remove: (id: string) => api.delete<void>(`/roles/${id}`),

  /** Référentiel global, non paginé : `index` renvoie la collection entière. */
  permissions: () => api.get<ApiResource<Permission[]>>('/permissions').then((r) => r.data),
}
