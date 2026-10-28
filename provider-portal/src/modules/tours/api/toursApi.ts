import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

import type { Tour, TourEvent, TourFilters, TourStop } from '../types'

export const toursApi = {
  list: (filters: TourFilters) =>
    api.get<Paginated<Tour>>('/tours', {
      query: { search: filters.search, status: filters.status, state: filters.state, page: filters.page, perPage: filters.perPage },
    }),

  get: async (id: string) => (await api.get<ApiEnvelope<Tour>>(`/tours/${id}`)).data,

  /** `null` retire l'affectation. Rien d'autre de la tournée n'est envoyé. */
  assign: async (id: string, payload: { driverId?: string | null; vehicleId?: string | null }) =>
    (await api.patch<ApiEnvelope<Tour>>(`/tours/${id}/resources`, payload)).data,

  /** Exécution : aucune heure n'est envoyée, le serveur fixe la sienne. */
  start: (id: string) => api.post<ApiEnvelope<Tour>>(`/tours/${id}/start`),

  complete: (id: string) => api.post<ApiEnvelope<Tour>>(`/tours/${id}/complete`),

  stop: (id: string, stopId: string, action: 'arrive' | 'complete' | 'skip', reason?: string) =>
    api.post<ApiEnvelope<TourStop>>(`/tours/${id}/stops/${stopId}/${action}`, reason === undefined ? undefined : { reason }),

  calendar: async (from: string, to: string) =>
    (await api.get<ApiEnvelope<TourEvent[]>>('/tours/calendar', { query: { from, to } })).data,
}
