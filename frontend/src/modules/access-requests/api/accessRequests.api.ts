import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { AccessRequest, AccessRequestPayload, AccessRequestStatus } from '../types/accessRequest'

/**
 * Les demandes d'accès, des deux côtés du guichet.
 *
 * `submit` est la seule route publique du module : elle part sans jeton, parce
 * qu'elle est appelée par quelqu'un qui n'en a pas encore. Elle ne rend qu'un
 * message — ni compte, ni organisation, ni jeton n'existent à ce stade.
 */
export const accessRequestsApi = {
  submit: (payload: AccessRequestPayload) =>
    api
      .post<ApiResource<{ message: string }>>('/access-requests', payload)
      .then((response) => response.data),

  list: (filters: { page: number; perPage: number; status?: AccessRequestStatus }) =>
    api.get<ApiCollection<AccessRequest>>('/access-requests', { query: { ...filters } }),

  approve: (id: string, note?: string) =>
    api
      .post<ApiResource<AccessRequest>>(`/access-requests/${id}/approve`, { note })
      .then((response) => response.data),

  reject: (id: string, note?: string) =>
    api
      .post<ApiResource<AccessRequest>>(`/access-requests/${id}/reject`, { note })
      .then((response) => response.data),
}
