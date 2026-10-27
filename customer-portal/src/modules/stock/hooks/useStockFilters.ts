import { useSearchParams } from 'react-router-dom'

import type { StockFilters } from '../types'

const KEYS = ['search', 'status', 'locationId', 'stockItemId', 'movementType', 'createdFrom', 'createdTo', 'state', 'sort', 'direction'] as const

/** Les filtres d'une liste du stock, conservés dans l'URL : un retour arrière retrouve la même liste. */
export function useStockFilters() {
  const [params, setParams] = useSearchParams()

  const filters: StockFilters = { page: Number(params.get('page') ?? '1') || 1 }
  // Nombre de lignes choisi au pied du tableau, comme au back-office.
  const perPage = Number(params.get('perPage'))
  if (perPage > 0) filters.perPage = perPage
  KEYS.forEach((key) => {
    const value = params.get(key)
    if (value) (filters as Record<string, unknown>)[key] = value
  })

  const update = (patch: Partial<StockFilters>) => {
    const next = new URLSearchParams(params)

    Object.entries(patch).forEach(([key, value]) => {
      if (value === undefined || value === '' || value === null) next.delete(key)
      else next.set(key, String(value))
    })

    if (!('page' in patch)) next.delete('page')
    setParams(next, { replace: true })
  }

  const toggleSort = (column: string) => {
    const same = (filters.sort ?? 'articleCode') === column
    update({ sort: column, direction: same && (filters.direction ?? 'asc') === 'asc' ? 'desc' : 'asc' })
  }

  return { filters, update, toggleSort, reset: () => setParams(new URLSearchParams(), { replace: true }) }
}
