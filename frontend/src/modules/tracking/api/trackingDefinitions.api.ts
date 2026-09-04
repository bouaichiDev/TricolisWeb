import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type {
  TrackingDefinitionFilters,
  TrackingDefinitionPayload,
  TrackingEventDefinition,
} from '../types/trackingDefinition'

/** Les étapes du parcours client, configurées par l'organisme. */
export const trackingDefinitionsApi = {
  list: (filters: TrackingDefinitionFilters) =>
    api.get<ApiCollection<TrackingEventDefinition>>('/tracking-event-definitions', {
      query: { ...filters },
    }),

  create: (payload: TrackingDefinitionPayload) =>
    api
      .post<ApiResource<TrackingEventDefinition>>('/tracking-event-definitions', payload)
      .then((response) => response.data),

  update: (id: string, payload: Partial<TrackingDefinitionPayload>) =>
    api
      .patch<ApiResource<TrackingEventDefinition>>(`/tracking-event-definitions/${id}`, payload)
      .then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/tracking-event-definitions/${id}`),
}
