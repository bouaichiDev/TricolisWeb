/**
 * Enveloppes de réponse du backend.
 *
 * Le contrat d'API garantit ces trois formes sur les 308 routes : `data` seul
 * pour une ressource, `data + meta + links` pour une liste paginée, et rien du
 * tout en 204. Les types ci-dessous sont la seule interprétation autorisée de
 * ces enveloppes.
 */

export interface ApiResource<T> {
  data: T
  meta: unknown[]
}

export interface PaginationMeta {
  currentPage: number
  perPage: number
  total: number
  lastPage: number
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface ApiCollection<T> {
  data: T[]
  meta: PaginationMeta
  links: PaginationLinks
}

/** Paramètres communs à toutes les listes du backend. */
export interface ListParams {
  page?: number
  perPage?: number
  search?: string
  sort?: string
  direction?: 'asc' | 'desc'
  createdFrom?: string
  createdTo?: string
}

export const DEFAULT_PER_PAGE = 25
