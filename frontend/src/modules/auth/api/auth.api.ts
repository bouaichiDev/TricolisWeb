import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'
import type { LoginPayload, MePayload } from '@/shared/types/auth'

/**
 * Les quatre appels d'authentification réellement exposés par le backend.
 *
 * Aucun n'exige l'en-tête d'organisation : ce sont des routes personnelles.
 */
export const authApi = {
  login: (email: string, password: string) =>
    api
      .post<ApiResource<LoginPayload>>('/auth/login', {
        email,
        password,
        device_name: 'backoffice',
      })
      .then((response) => response.data),

  me: () => api.get<ApiResource<MePayload>>('/auth/me').then((response) => response.data),

  logout: () => api.post<void>('/auth/logout'),

  logoutAll: () => api.post<void>('/auth/logout-all'),
}
