import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Organization, OrganizationFilters } from '../types/organization'

export interface OrganizationPayload {
  code: string
  name: string
  legalName?: string | null
  registrationNumber?: string | null
  taxNumber?: string | null
  email?: string | null
  phone?: string | null
  preferredLanguage?: string
  timezone?: string
  currencyCode?: string
  status?: string
}

/**
 * Ces routes ne demandent pas l'en-tête `X-Organization-Id` : elles servent
 * justement à choisir l'organisation active. La liste ne renvoie que les
 * organisations dont l'utilisateur connecté est membre.
 */
export const organizationsApi = {
  list: (filters: OrganizationFilters) =>
    api.get<ApiCollection<Organization>>('/organizations', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<Organization>>(`/organizations/${id}`).then((response) => response.data),

  create: (payload: OrganizationPayload) =>
    api.post<ApiResource<Organization>>('/organizations', payload).then((r) => r.data),

  update: (id: string, payload: Partial<OrganizationPayload>) =>
    api.patch<ApiResource<Organization>>(`/organizations/${id}`, payload).then((r) => r.data),

  remove: (id: string) => api.delete<void>(`/organizations/${id}`),
}
