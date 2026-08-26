import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { Tour, TourFilters } from '../types/tour'

export const toursApi = {
  list: (filters: TourFilters) =>
    api.get<ApiCollection<Tour>>('/tours', { query: { ...filters } }),

  get: (id: string) => api.get<ApiResource<Tour>>(`/tours/${id}`).then((r) => r.data),

  /**
   * Faire passer la tournée d'un état à un autre.
   *
   * C'est par là que se valide un brouillon et qu'il s'annule : le référentiel
   * dit quels passages existent, le serveur les applique.
   */
  changeStatus: (id: string, status: string) =>
    api.post<ApiResource<Tour>>(`/tours/${id}/status`, { status }).then((r) => r.data),
}
