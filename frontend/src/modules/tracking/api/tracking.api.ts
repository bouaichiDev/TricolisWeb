import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type {
  TrackingEvent,
  TrackingEventFilters,
  TrackingEventPayload,
} from '../types/trackingEvent'

/**
 * Suivi d'exécution.
 *
 * `POST /tracking-events` est la **seule** écriture : il n'existe ni `update`
 * ni `destroy`. Un événement de suivi est un fait daté ; on ne réécrit pas
 * l'histoire, on ajoute un événement qui la corrige.
 */
export const trackingApi = {
  /** Événements d'une commande, tournées et arrêts compris. */
  byOrder: (orderId: string, filters: TrackingEventFilters) =>
    api.get<ApiCollection<TrackingEvent>>(`/orders/${orderId}/tracking-events`, {
      query: { ...filters },
    }),

  get: (id: string) =>
    api
      .get<ApiResource<TrackingEvent>>(`/tracking-events/${id}`)
      .then((response) => response.data),

  create: (payload: TrackingEventPayload) =>
    api
      .post<ApiResource<TrackingEvent>>('/tracking-events', payload)
      .then((response) => response.data),
}
