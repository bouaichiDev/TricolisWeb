import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type {
  Status,
  StatusDefault,
  StatusPropagationList,
  StatusPropagationPayload,
  StatusFilters,
  StatusPayload,
  StatusTransition,
  StatusTransitionInput,
} from '../types/status'

/**
 * Référentiel des statuts, commun à toute la plateforme.
 *
 * Les routes vivent hors du groupe `organization` : un compte plateforme n'a
 * pas d'organisation active, et exiger l'en-tête lui fermerait l'écran. C'est
 * `StatusPolicy` qui décide, pas la présence de l'en-tête.
 */
export const statusesApi = {
  list: (filters: StatusFilters) =>
    api.get<ApiCollection<Status>>('/statuses', { query: { ...filters } }),

  /** Entités auxquelles un statut peut se rapporter, dérivées côté serveur. */
  sources: () =>
    api.get<ApiResource<string[]>>('/statuses/sources').then((response) => response.data),

  /** Statut par défaut de chaque entité, et ceux qu'on peut lui choisir. */
  defaults: () =>
    api.get<ApiResource<StatusDefault[]>>('/statuses/defaults').then((response) => response.data),

  /** `null` retire le choix : la colonne reprend sa propre valeur par défaut. */
  setDefault: (source: string, statusId: string | null) =>
    api
      .put<ApiResource<StatusDefault[]>>(`/statuses/defaults/${source}`, { statusId })
      .then((response) => response.data),

  /** Règles de propagation, avec la hiérarchie qui dit qui contient qui. */
  propagations: () =>
    api
      .get<ApiResource<StatusPropagationList>>('/status-propagations')
      .then((response) => response.data),

  createPropagation: (payload: StatusPropagationPayload) =>
    api.post<ApiResource<unknown>>('/status-propagations', payload).then((response) => response.data),

  updatePropagation: (id: string, payload: Partial<StatusPropagationPayload>) =>
    api
      .patch<ApiResource<unknown>>(`/status-propagations/${id}`, payload)
      .then((response) => response.data),

  removePropagation: (id: string) => api.delete<void>(`/status-propagations/${id}`),

  create: (payload: StatusPayload) =>
    api.post<ApiResource<Status>>('/statuses', payload).then((response) => response.data),

  update: (id: string, payload: Partial<StatusPayload>) =>
    api.patch<ApiResource<Status>>(`/statuses/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/statuses/${id}`),

  transitions: (id: string) =>
    api
      .get<ApiResource<StatusTransition[]>>(`/statuses/${id}/transitions`)
      .then((response) => response.data),

  /**
   * Remplace l'ensemble des transitions au départ d'un statut.
   *
   * Un envoi complet plutôt qu'un ajout par arête : dessiner un cycle de vie se
   * fait d'un bloc, et une mise à jour partielle laisserait, le temps de la
   * séquence, un graphe que personne n'a voulu.
   */
  syncTransitions: (id: string, transitions: StatusTransitionInput[]) =>
    api
      .put<ApiResource<StatusTransition[]>>(`/statuses/${id}/transitions`, { transitions })
      .then((response) => response.data),
}
