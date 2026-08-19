import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { Status, StatusFilters, StatusPayload } from '../types/status'

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

  create: (payload: StatusPayload) =>
    api.post<ApiResource<Status>>('/statuses', payload).then((response) => response.data),

  update: (id: string, payload: Partial<StatusPayload>) =>
    api.patch<ApiResource<Status>>(`/statuses/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/statuses/${id}`),
}
