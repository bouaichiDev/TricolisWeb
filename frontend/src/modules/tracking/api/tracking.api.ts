import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { TrackingEvent, TrackingEventFilters } from '../types/trackingEvent'

/**
 * Suivi d'exécution.
 *
 * **En lecture seule depuis le web.** Les événements naissent des changements
 * de statut décrits dans le parcours de l'organisation, ou du terminal du
 * chauffeur ; `POST /tracking-events` existe côté serveur pour eux, pas pour
 * une saisie commande par commande.
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
