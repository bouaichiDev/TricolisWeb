/**
 * Statut du référentiel commun — `StatusResource`.
 *
 * `code` est la valeur réellement stockée dans les colonnes `status` du
 * domaine — `orders.status`, `packages.status` — et `source` dit de quelle
 * entité il s'agit : « draft » n'a pas le même sens pour une commande et pour
 * un colis.
 *
 * `status` est l'identifiant numérique du statut, unique dans sa source.
 */
export interface Status {
  id: string
  source: string
  status: number
  code: string
  label: string
  icon: string | null
  active: boolean
  /** Ce statut déclenche-t-il un envoi au client ? */
  isToSend: boolean
  position: number | null
  createdAt: string
  updatedAt: string
}

export interface StatusFilters {
  page: number
  perPage: number
  search?: string
  source?: string
  active?: boolean
  sort?: string
  direction?: 'asc' | 'desc'
}

/** Colonnes acceptées par `StatusController::index` ; toute autre renvoie 422. */
export const STATUS_SORTABLE = ['source', 'status', 'code', 'label', 'position'] as const

export interface StatusPayload {
  source?: string
  status: number
  code: string
  label: string
  icon?: string | null
  active?: boolean
  isToSend?: boolean
  position?: number | null
}
