import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Member, MemberFilters } from '../types/member'

export interface MemberCreatePayload {
  firstName: string
  lastName: string
  email: string
  phone?: string | null
  password: string
  password_confirmation: string
  preferredLanguage: string
  isOwner: boolean
  isPrimary: boolean
  status: string
  roleIds?: string[]
}

/** `email` est absent : il se modifie par `PATCH /users/{id}`, pas ici. */
export interface MemberUpdatePayload {
  firstName?: string
  lastName?: string
  phone?: string | null
  preferredLanguage?: string
  isOwner?: boolean
  isPrimary?: boolean
  status?: string
  roleIds?: string[]
}

/**
 * `DELETE` ne supprime pas : le rattachement passe au statut `disabled`, pour
 * préserver l'historique d'audit qui le référence.
 */
export const membersApi = {
  list: (filters: MemberFilters) =>
    api.get<ApiCollection<Member>>('/organization-users', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<Member>>(`/organization-users/${id}`).then((r) => r.data),

  create: (payload: MemberCreatePayload) =>
    api.post<ApiResource<Member>>('/organization-users', payload).then((r) => r.data),

  update: (id: string, payload: MemberUpdatePayload) =>
    api.patch<ApiResource<Member>>(`/organization-users/${id}`, payload).then((r) => r.data),

  disable: (id: string) => api.delete<void>(`/organization-users/${id}`),

  /**
   * Envoie au membre un lien de réinitialisation.
   *
   * C'est le chemin par défaut : l'administrateur ne connaît jamais le mot de
   * passe, et le membre le choisit lui-même.
   */
  sendPasswordResetLink: (id: string) =>
    api
      .post<ApiResource<{ email: string }>>(`/organization-users/${id}/password-reset-link`)
      .then((response) => response.data),

  /**
   * Pose un mot de passe pour le membre.
   *
   * À réserver aux comptes qui ne relèvent pas de courriel : l'administrateur
   * connaît alors le mot de passe, ce que le lien évite.
   */
  setPassword: (id: string, password: string) =>
    api.put<void>(`/organization-users/${id}/password`, {
      password,
      password_confirmation: password,
    }),
}
