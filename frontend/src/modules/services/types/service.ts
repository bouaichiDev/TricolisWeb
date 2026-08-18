/**
 * Service du référentiel — champs relevés sur `ServiceResource`.
 *
 * Pas de `category` : le §26 du premier prompt la citait, la ressource ne
 * l'expose pas. Le §52 du prompt corrigé interdit explicitement de l'afficher.
 */
export interface Service {
  id: string
  organizationId: string
  code: string
  name: string
  unit: string | null
  defaultDurationMinutes: number | null
  billableToCustomer: boolean
  payableToProvider: boolean
  /** Le service exige-t-il une adresse d'exécution ? */
  requiresAddress: boolean
  /** Le service exige-t-il un contact sur place ? */
  requiresContact: boolean
  status: string
}

export const SERVICE_STATUSES = ['active', 'inactive'] as const

export interface ServiceFilters {
  page: number
  perPage: number
  search?: string
  status?: string
  /** Colonnes acceptées par `ServiceController::index` : `code`, `name`. */
  sort?: string
  direction?: 'asc' | 'desc'
}
