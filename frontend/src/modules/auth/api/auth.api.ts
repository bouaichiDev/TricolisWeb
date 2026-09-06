import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'
import type { LoginPayload, MePayload } from '@/shared/types/auth'

/**
 * Les appels d'authentification réellement exposés par le backend.
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

  /**
   * Demander le lien qui rendra l'acces.
   *
   * La reponse est la meme pour une adresse connue et pour une inconnue :
   * l'ecran ne peut donc pas dire si le compte existe, et c'est voulu.
   */
  forgotPassword: (email: string) =>
    api
      .post<ApiResource<{ message: string }>>('/auth/forgot-password', { email })
      .then((response) => response.data),

  logout: () => api.post<void>('/auth/logout'),

  logoutAll: () => api.post<void>('/auth/logout-all'),

  /**
   * Pose le nouveau mot de passe à partir du jeton reçu par courriel.
   *
   * Le jeton et l'adresse viennent du lien : les retaper serait absurde, et
   * l'adresse sert au serveur à retrouver le jeton qu'il a émis.
   */
  resetPassword: (payload: {
    token: string
    email: string
    password: string
  }) =>
    api.post<{ message: string }>('/auth/reset-password', {
      ...payload,
      password_confirmation: payload.password,
    }),
}
