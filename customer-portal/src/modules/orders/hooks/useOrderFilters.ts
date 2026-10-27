import { useSearchParams } from 'react-router-dom'

import type { OrderFilters } from '../types'

const KEYS = ['search', 'status', 'dateFrom', 'dateTo', 'service', 'sort', 'direction'] as const

/**
 * Les filtres de la liste, conservés dans l'URL : un lien partagé ou un
 * retour arrière retrouve exactement la même liste.
 */
export function useOrderFilters() {
  const [params, setParams] = useSearchParams()

  const filters: OrderFilters = { page: Number(params.get('page') ?? '1') || 1 }
  // Nombre de lignes choisi au pied du tableau, comme au back-office.
  const perPage = Number(params.get('perPage'))
  if (perPage > 0) filters.perPage = perPage
  KEYS.forEach((key) => {
    const value = params.get(key)
    if (value) (filters as Record<string, unknown>)[key] = value
  })

  const update = (patch: Partial<OrderFilters>) => {
    const next = new URLSearchParams(params)

    Object.entries(patch).forEach(([key, value]) => {
      if (value === undefined || value === '' || value === null) next.delete(key)
      else next.set(key, String(value))
    })

    // Un nouveau filtre ramène à la première page ; changer de page, non.
    if (!('page' in patch)) next.delete('page')
    setParams(next, { replace: true })
  }

  const toggleSort = (column: string) => {
    const same = (filters.sort ?? 'orderDate') === column
    update({ sort: column, direction: same && (filters.direction ?? 'desc') === 'desc' ? 'asc' : 'desc' })
  }

  return { filters, update, toggleSort, reset: () => setParams(new URLSearchParams(), { replace: true }) }
}
