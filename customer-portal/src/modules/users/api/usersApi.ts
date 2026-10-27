import { api } from '@/shared/api/client'
import type { ApiEnvelope, MemberStatus, Paginated, PortalRole, PortalUser } from '@/shared/api/types'

export interface UserFilters {
  search?: string
  status?: MemberStatus | ''
  page?: number
  perPage?: number
}

export interface InvitePayload {
  email: string
  firstName?: string
  lastName?: string
  roleId?: string | null
}

export interface UpdateUserPayload {
  firstName: string
  lastName: string
  phone: string | null
}

const one = async (request: Promise<ApiEnvelope<PortalUser>>) => (await request).data

/**
 * Utilisateurs du portail. Aucun identifiant de client n'est envoyé : le
 * backend borne tout au client du jeton.
 */
export const usersApi = {
  list: (filters: UserFilters) =>
    api.get<Paginated<PortalUser>>('/users', { query: { ...filters, perPage: filters.perPage ?? 25 } }),
  get: (id: string) => one(api.get(`/users/${id}`)),
  invite: (payload: InvitePayload) => one(api.post('/users/invite', payload)),
  update: (id: string, payload: UpdateUserPayload) => one(api.put(`/users/${id}`, payload)),
  enable: (id: string) => one(api.post(`/users/${id}/enable`)),
  disable: (id: string) => one(api.post(`/users/${id}/disable`)),
  resendInvitation: (id: string) => one(api.post(`/users/${id}/resend-invitation`)),
  assignRoles: (id: string, roleIds: string[]) => one(api.put(`/users/${id}/roles`, { roleIds })),
  roles: async () => (await api.get<ApiEnvelope<PortalRole[]>>('/roles')).data,
}
