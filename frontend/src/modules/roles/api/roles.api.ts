import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Permission, Role, RoleFilters } from '../types/role'

/**
 * `scope`, `isSystem` et `organizationId` sont absents des deux charges utiles.
 *
 * L'API ne les lit plus : la portée vaut toujours « organisation », le drapeau
 * système toujours `false`, et l'organisation est celle de l'en-tête
 * `X-Organization-Id`. Les envoyer serait sans effet — les typer inviterait à
 * croire le contraire.
 */
export interface RoleCreatePayload {
  code: string
  name: string
  status: string
  permissionIds?: string[]
}

/** `code` n'est pas modifiable : il identifie le rôle pour les vérifications. */
export interface RoleUpdatePayload {
  name?: string
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
