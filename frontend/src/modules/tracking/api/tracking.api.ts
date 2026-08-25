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

/**
 * Positions du véhicule qui exécute la commande.
 *
 * Le jeton du fournisseur reste au serveur : cette route l'appelle pour nous.
 * `reason` dit pourquoi la liste est vide — rien de configuré, aucune tournée
 * référencée — plutôt que de laisser deviner.
 */
export interface OrderPositions {
  points: { latitude: number; longitude: number; occurredAt: string | null }[]
  reason: 'not_configured' | 'no_reference' | null
}

export const orderPositionsApi = {
  get: (orderId: string) =>
    api
      .get<ApiResource<OrderPositions>>(`/orders/${orderId}/positions`)
      .then((response) => response.data),
}
