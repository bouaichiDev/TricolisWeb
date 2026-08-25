import type { CompactUser } from '@/modules/tracking/types/trackingEvent'

/**
 * Réclamation — `ClaimResource`.
 *
 * `claimType` et `status` sont des **chaînes libres** (`max:64` / `max:32`) :
 * aucune énumération PHP ne les borne. `claim` est en revanche une source du
 * référentiel de statuts, où un administrateur plateforme peut les décrire.
 *
 * Ni `claimNumber`, ni `severity`, ni `priority`, ni `comments` : le diagramme
 * n'en porte pas, et le §13 interdit de les inventer.
 */
export interface Claim {
  id: string
  organizationId: string
  customerId: string
  orderId: string | null
  /** Absent de la ressource de liste, présent au détail. */
  orderServiceId?: string | null
  tourId: string | null
  title: string
  description?: string | null
  claimType: string
  cause?: string | null
  decision?: string | null
  followUp?: string | null
  result: string | null
  cost: number | string | null
  status: string
  createdBy?: string | null
  responsibleUserId: string | null
  createdAt: string
  closedAt: string | null
  /** Présent en liste, pour éviter un appel par ligne. */
  customerName?: string | null
  creator?: CompactUser
  responsibleUser?: CompactUser
}

/** Filtres acceptés par `ListClaimRequest`. Aucun `severity` : il n'existe pas. */
export interface ClaimFilters {
  page: number
  perPage: number
  search?: string
  status?: string
  customerId?: string
  orderId?: string
  claimType?: string
  responsibleUserId?: string
  sort?: string
  direction?: 'asc' | 'desc'
}

/**
 * Charge utile de `StoreClaimRequest`.
 *
 * Le traitement — `decision`, `followUp`, `result`, `cost`, `closedAt` — n'y
 * figure pas : le serveur ne l'accepte **pas** à la création. Une réclamation
 * naît ouverte ; ce qu'on en a fait s'écrit ensuite.
 */
export interface ClaimPayload {
  customerId: string
  orderId?: string | null
  orderServiceId?: string | null
  tourId?: string | null
  title: string
  description?: string | null
  claimType: string
  cause?: string | null
  status: string
  responsibleUserId?: string | null
}

/** Charge utile de `UpdateClaimRequest`, traitement compris. */
export interface ClaimUpdatePayload extends Partial<Omit<ClaimPayload, 'customerId'>> {
  decision?: string | null
  followUp?: string | null
  result?: string | null
  cost?: number | null
  closedAt?: string | null
}
