import type { BillableServiceFilters } from '../types/invoice'

/**
 * L'état des filtres de colonne du sélecteur.
 *
 * Tout est chaîne, y compris les bornes numériques : un champ vidé doit rester
 * vide, et `NaN` ou `0` s'y glisseraient en changeant le sens du filtre.
 */
export interface BillableColumnFilters {
  /** Plusieurs prestations à la fois : elles se cumulent en « ou ». */
  service: string[]
  order: string
  reference: string
  periodFrom: string
  periodTo: string
  address: string
  quantityMin: string
  quantityMax: string
  priceMin: string
  priceMax: string
}

export const EMPTY_BILLABLE_FILTERS: BillableColumnFilters = {
  service: [],
  order: '',
  reference: '',
  periodFrom: '',
  periodTo: '',
  address: '',
  quantityMin: '',
  quantityMax: '',
  priceMin: '',
  priceMax: '',
}

/**
 * Traduit l'état de l'écran en paramètres de requête.
 *
 * Les champs vides sont **omis**, jamais envoyés vides : le serveur validerait
 * `quantityMin=` comme une valeur, et une chaîne vide n'est pas un nombre.
 */
export function toBillableQuery(filters: BillableColumnFilters): BillableServiceFilters {
  const query: BillableServiceFilters = {}

  if (filters.service.length > 0) query.service = filters.service
  if (filters.order.trim() !== '') query.order = filters.order.trim()
  if (filters.reference.trim() !== '') query.reference = filters.reference.trim()
  if (filters.address.trim() !== '') query.address = filters.address.trim()
  if (filters.periodFrom !== '') query.periodFrom = filters.periodFrom
  if (filters.periodTo !== '') query.periodTo = filters.periodTo

  for (const key of ['quantityMin', 'quantityMax', 'priceMin', 'priceMax'] as const) {
    const value = Number.parseFloat(filters[key])
    if (!Number.isNaN(value)) query[key] = value
  }

  return query
}

/** Un filtre est-il posé ? Sert à proposer de tout effacer, et pas avant. */
export function hasBillableFilter(filters: BillableColumnFilters): boolean {
  return Object.values(filters).some((value) =>
    Array.isArray(value) ? value.length > 0 : value !== '',
  )
}
