/** Auteur d'un enregistrement — `UserCompactResource`. */
export interface CompactUser {
  id: string
  firstName: string
  lastName: string
  email: string
}

/**
 * Événement de suivi — `TrackingEventResource`.
 *
 * `eventType` et `status` sont des **chaînes libres** côté serveur
 * (`max:64` / `max:32`) : aucune énumération PHP ne les borne, et en dresser une
 * ici inventerait un vocabulaire métier que le backend ne connaît pas.
 *
 * Ni `driverId`, ni `vehicleId`, ni `metadata` : le diagramme n'en porte pas.
 */
export interface TrackingEvent {
  id: string
  organizationId: string
  orderId: string
  orderServiceId: string | null
  tourId: string | null
  tourStopId: string | null
  eventType: string
  status: string
  /** Absente de la ressource de liste, présente au détail. */
  description?: string | null
  latitude: number | string | null
  longitude: number | string | null
  occurredAt: string
  createdBy: string | null
  creator?: CompactUser
}

/**
 * Filtres acceptés par `ListTrackingEventRequest`.
 *
 * Il n'y a **pas** de filtre `status` : le serveur ne l'accepte pas, et
 * l'ajouter ferait échouer la requête en 422.
 */
export interface TrackingEventFilters {
  page: number
  perPage: number
  eventType?: string
  occurredFrom?: string
  occurredTo?: string
  sort?: string
  direction?: 'asc' | 'desc'
}

/** Vrai quand l'événement porte une position exploitable. */
export function hasCoordinates(event: TrackingEvent): boolean {
  return event.latitude !== null && event.longitude !== null
}
